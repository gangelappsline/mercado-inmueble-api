<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Interes;
use App\Models\Propiedad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Embudo comercial: registro de interés, respuestas del anunciante, mensajes
 * y agenda de citas.
 */
class CitaEInteresTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El cliente registra interés: se crea el lead, el hilo, la métrica y se
     * notifica al anunciante.
     */
    public function test_cliente_registra_interes_y_se_notifica_al_anunciante(): void
    {
        Mail::fake();
        Notification::fake();

        $propiedad = $this->propiedadPublicada();
        $this->autenticar($this->usuario(Role::Cliente));

        $respuesta = $this->postJson("/api/v1/cliente/propiedades/{$propiedad->id}/interes", [
            'mensaje' => 'Hola, me interesa la propiedad. ¿Sigue disponible?',
            'preferencia_contacto' => 'whatsapp',
        ], $this->cabeceras());

        $respuesta->assertCreated()
            ->assertJsonPath('data.estado.value', 'nuevo')
            ->assertJsonPath('data.propiedad.id', $propiedad->id);

        $this->assertDatabaseHas('intereses', ['propiedad_id' => $propiedad->id, 'estado' => 'nuevo']);
        $this->assertDatabaseHas('hilos', ['interes_id' => Interes::query()->latest('id')->value('id')]);
        $this->assertDatabaseHas('mensajes', ['cuerpo' => 'Hola, me interesa la propiedad. ¿Sigue disponible?']);
        $this->assertDatabaseHas('reportes', ['propiedad_id' => $propiedad->id, 'tipo' => 'contacto']);
        $this->assertDatabaseCount('notifications', 1);

        $this->assertSame(1, (int) $propiedad->refresh()->contactos_count);
    }

    /**
     * Un cliente no puede registrar dos veces el mismo interés (409).
     */
    public function test_interes_duplicado_devuelve_409(): void
    {
        Mail::fake();
        Notification::fake();

        $propiedad = $this->propiedadPublicada();
        $this->autenticar($this->usuario(Role::Cliente));

        $this->postJson("/api/v1/cliente/propiedades/{$propiedad->id}/interes", [
            'mensaje' => 'Primera consulta sobre la propiedad publicada.',
        ], $this->cabeceras())->assertCreated();

        $this->postJson("/api/v1/cliente/propiedades/{$propiedad->id}/interes", [
            'mensaje' => 'Segunda consulta sobre la misma propiedad.',
        ], $this->cabeceras())
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    /**
     * El anunciante responde al interesado y cambia el estado del lead.
     */
    public function test_anunciante_responde_y_cambia_el_estado(): void
    {
        Mail::fake();
        Notification::fake();

        $usuario = $this->usuario(Role::Inmobiliaria);
        $propiedad = Propiedad::factory()->delPropietario($usuario->propietario())->publicada()->create();

        $cliente = $this->usuario(Role::Cliente);
        $interes = Interes::factory()->create([
            'propiedad_id' => $propiedad->id,
            'cliente_id' => $cliente->cliente->id,
        ]);

        $this->autenticar($usuario);

        $this->postJson("/api/v1/inmobiliaria/interesados/{$interes->id}/responder", [
            'cuerpo' => 'Gracias por tu interés, puedo mostrarte la propiedad el sábado.',
        ], $this->cabeceras())->assertCreated();

        $this->patchJson("/api/v1/inmobiliaria/interesados/{$interes->id}/estado", [
            'estado' => 'atendido',
        ], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.estado.value', 'atendido');

        $this->assertDatabaseHas('mensajes', ['hilo_id' => $interes->hilo->id]);
    }

    /**
     * El cliente no ve los interesados de un anunciante que no le corresponde.
     */
    public function test_cliente_no_accede_a_los_interesados_del_panel(): void
    {
        $this->autenticar($this->usuario(Role::Cliente));

        $this->getJson('/api/v1/inmobiliaria/interesados', $this->cabeceras())->assertForbidden();
    }

    /**
     * Ciclo completo de citas: solicitar, rechazar solapamiento, confirmar,
     * reprogramar y cancelar.
     */
    public function test_ciclo_completo_de_una_cita(): void
    {
        Mail::fake();
        Notification::fake();

        $anunciante = $this->usuario(Role::Inmobiliaria);
        $propiedad = Propiedad::factory()->delPropietario($anunciante->propietario())->publicada()->create();
        $cliente = $this->usuario(Role::Cliente);

        $fecha = now()->addDays(5)->toDateString();

        $this->autenticar($cliente);

        $creada = $this->postJson("/api/v1/cliente/propiedades/{$propiedad->id}/citas", [
            'fecha' => $fecha,
            'hora' => '10:00',
            'tipo' => 'visita',
            'lugar' => 'Av. Arce #2450, La Paz',
            'duracion_minutos' => 45,
        ], $this->cabeceras());

        $creada->assertCreated()->assertJsonPath('data.estado.value', 'pendiente');

        $citaId = (int) $creada->json('data.id');

        // Solapamiento con la misma agenda del anunciante
        $this->postJson("/api/v1/cliente/propiedades/{$propiedad->id}/citas", [
            'fecha' => $fecha,
            'hora' => '10:15',
            'tipo' => 'visita',
            'lugar' => 'Av. Arce #2450, La Paz',
        ], $this->cabeceras())->assertStatus(409);

        // El anunciante confirma
        $this->autenticar($anunciante);

        $this->patchJson("/api/v1/inmobiliaria/citas/{$citaId}/confirmar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.estado.value', 'confirmada');

        $this->getJson('/api/v1/inmobiliaria/citas/agenda?fecha='.$fecha, $this->cabeceras())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // El cliente reprograma y luego cancela
        $this->autenticar($cliente);

        $this->patchJson("/api/v1/cliente/citas/{$citaId}/reprogramar", [
            'fecha' => now()->addDays(7)->toDateString(),
            'hora' => '15:30',
            'motivo' => 'Me surgió un compromiso a esa hora.',
        ], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.estado.value', 'reprogramada');

        $this->patchJson("/api/v1/cliente/citas/{$citaId}/cancelar", [
            'motivo' => 'Ya no podré asistir a la visita.',
        ], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.estado.value', 'cancelada');

        $this->assertDatabaseHas('citas', ['id' => $citaId, 'estado' => 'cancelada']);
    }

    /**
     * La cita no puede solicitarse fuera de la ventana permitida (90 días).
     */
    public function test_cita_fuera_de_rango_devuelve_422(): void
    {
        $propiedad = $this->propiedadPublicada();
        $this->autenticar($this->usuario(Role::Cliente));

        $this->postJson("/api/v1/cliente/propiedades/{$propiedad->id}/citas", [
            'fecha' => now()->addDays((int) config('mercado.reglas.dias_anticipacion_cita_max', 90) + 10)->toDateString(),
            'hora' => '11:00',
            'tipo' => 'llamada',
        ], $this->cabeceras())
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * El anunciante cierra el hilo y el cliente ya no puede responder (409).
     */
    public function test_hilo_cerrado_no_admite_respuestas(): void
    {
        Mail::fake();
        Notification::fake();

        $anunciante = $this->usuario(Role::Inmobiliaria);
        $propiedad = Propiedad::factory()->delPropietario($anunciante->propietario())->publicada()->create();
        $cliente = $this->usuario(Role::Cliente);
        $interes = Interes::factory()->create([
            'propiedad_id' => $propiedad->id,
            'cliente_id' => $cliente->cliente->id,
        ]);

        $this->autenticar($anunciante);

        $this->getJson('/api/v1/inmobiliaria/mensajes', $this->cabeceras())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->patchJson("/api/v1/inmobiliaria/mensajes/{$interes->hilo->id}/cerrar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.cerrado', true);

        $this->autenticar($cliente);

        $this->postJson("/api/v1/cliente/mensajes/{$interes->hilo->id}", [
            'cuerpo' => '¿Sigue disponible la propiedad?',
        ], $this->cabeceras())
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.hilo_cerrado'));
    }

    /**
     * Crea una propiedad publicada con fotos y su anunciante.
     */
    private function propiedadPublicada(): Propiedad
    {
        $anunciante = $this->usuario(Role::Inmobiliaria);

        return Propiedad::factory()
            ->delPropietario($anunciante->propietario())
            ->publicada()
            ->conFotos(2)
            ->create();
    }
}
