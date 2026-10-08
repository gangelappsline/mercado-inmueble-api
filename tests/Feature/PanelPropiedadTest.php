<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Panel del anunciante: CRUD de publicaciones, medios, publicación y pausa.
 */
class PanelPropiedadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La creación con publicación directa exige fotos: sin ellas la propiedad
     * queda en borrador y se informa la advertencia.
     */
    public function test_crear_propiedad_sin_fotos_no_se_publica(): void
    {
        $usuario = $this->autenticar($this->usuario(Role::Inmobiliaria));

        $respuesta = $this->postJson('/api/v1/inmobiliaria/propiedades', $this->datosDePropiedad() + ['publicar' => true], $this->cabeceras());

        $respuesta->assertCreated()
            ->assertJsonPath('data.estado.value', 'borrador')
            ->assertJsonPath('meta.advertencia', __('messages.propiedad_no_publicable'));

        $this->assertDatabaseHas('propiedades', [
            'propietario_id' => $usuario->inmobiliaria->id,
            'estado' => 'borrador',
        ]);
    }

    /**
     * Con fotos, la propiedad se publica y aparece en el catálogo público.
     */
    public function test_crear_propiedad_con_fotos_la_publica(): void
    {
        Storage::fake('public');
        $this->autenticar($this->usuario(Role::Inmobiliaria));

        $respuesta = $this->postJson('/api/v1/inmobiliaria/propiedades', $this->datosDePropiedad() + [
            'fotos' => [
                UploadedFile::fake()->image('sala.jpg', 1200, 800),
                UploadedFile::fake()->image('cocina.jpg', 1200, 800),
            ],
            'publicar' => true,
        ], $this->cabeceras());

        $respuesta->assertCreated()->assertJsonPath('data.estado.value', 'publicada');

        $propiedad = Propiedad::query()->latest('id')->firstOrFail();

        $this->assertDatabaseCount('propiedad_fotos', 2);
        $this->assertNotNull($propiedad->publicada_en);
        $this->assertDatabaseHas('propiedad_fotos', ['propiedad_id' => $propiedad->id, 'es_principal' => true, 'orden' => 0]);
    }

    /**
     * La publicación valida requisitos mínimos (título, precio, ubicación y fotos).
     */
    public function test_publicar_un_borrador_incompleto_devuelve_422(): void
    {
        $usuario = $this->usuario(Role::Inmobiliaria);
        $propiedad = Propiedad::factory()->delPropietario($usuario->propietario())->borrador()->create(['precio' => 0]);

        $this->autenticar($usuario);

        $this->postJson("/api/v1/inmobiliaria/propiedades/{$propiedad->id}/publicar", [], $this->cabeceras())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('messages.propiedad_no_publicable'));
    }

    /**
     * Un anunciante no puede ver ni modificar publicaciones de otro (403).
     */
    public function test_aislamiento_entre_anunciantes(): void
    {
        $propietarioA = $this->usuario(Role::Inmobiliaria);
        $propiedad = Propiedad::factory()->delPropietario($propietarioA->propietario())->publicada()->create();

        $this->autenticar($this->usuario(Role::Vendedor));

        $this->getJson("/api/v1/vendedor/propiedades/{$propiedad->id}", $this->cabeceras())->assertForbidden();
        $this->patchJson("/api/v1/vendedor/propiedades/{$propiedad->id}", ['titulo' => 'Intento de cambio de título'], $this->cabeceras())->assertForbidden();
    }

    /**
     * Un cliente no accede al panel de anunciantes.
     */
    public function test_un_cliente_no_accede_al_panel_de_anunciantes(): void
    {
        $this->autenticar($this->usuario(Role::Cliente));

        $this->getJson('/api/v1/inmobiliaria/propiedades', $this->cabeceras())
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    /**
     * Flujo de medios: agregar fotos, marcar principal, reordenar, video y borrar.
     */
    public function test_gestion_de_medios_de_una_publicacion(): void
    {
        Storage::fake('public');
        Storage::fake('private');

        $usuario = $this->usuario(Role::Vendedor);
        $propiedad = Propiedad::factory()->delPropietario($usuario->propietario())->borrador()->conFotos(1)->create();

        $this->autenticar($usuario);

        // Fotos
        $subida = $this->postJson("/api/v1/vendedor/propiedades/{$propiedad->id}/fotos", [
            'fotos' => [
                UploadedFile::fake()->image('frente.jpg', 1000, 700),
                UploadedFile::fake()->image('fondo.jpg', 1000, 700),
            ],
            'principal' => true,
        ], $this->cabeceras());

        $subida->assertCreated();
        $this->assertDatabaseCount('propiedad_fotos', 3);

        $ids = $propiedad->fotos()->orderBy('id')->pluck('id')->all();

        $this->patchJson("/api/v1/vendedor/propiedades/{$propiedad->id}/fotos/orden", [
            'fotos' => array_reverse($ids),
        ], $this->cabeceras())->assertOk();

        $this->assertSame($ids[2], (int) $propiedad->fotos()->orderBy('orden')->value('id'));

        $this->patchJson("/api/v1/vendedor/propiedades/{$propiedad->id}/fotos/{$ids[0]}/principal", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.es_principal', true);

        $this->deleteJson("/api/v1/vendedor/propiedades/{$propiedad->id}/fotos/{$ids[1]}", [], $this->cabeceras())->assertOk();

        // Video (sólo uno por propiedad)
        $this->postJson("/api/v1/vendedor/propiedades/{$propiedad->id}/video", [
            'video' => UploadedFile::fake()->create('tour.mp4', 512, 'video/mp4'),
        ], $this->cabeceras())->assertCreated();

        $this->assertDatabaseCount('propiedad_videos', 1);

        $this->postJson("/api/v1/vendedor/propiedades/{$propiedad->id}/video", [
            'video' => UploadedFile::fake()->create('tour2.mp4', 512, 'video/mp4'),
        ], $this->cabeceras())->assertStatus(409);

        $this->deleteJson("/api/v1/vendedor/propiedades/{$propiedad->id}/video", [], $this->cabeceras())->assertOk();
        $this->assertDatabaseCount('propiedad_videos', 0);
    }

    /**
     * Pausar y cerrar la operación cambian el estado con trazabilidad.
     */
    public function test_pausar_y_cerrar_operacion(): void
    {
        $usuario = $this->usuario(Role::Inmobiliaria);
        $propiedad = Propiedad::factory()->delPropietario($usuario->propietario())->publicada()->conFotos(1)->create();

        $this->autenticar($usuario);

        $this->postJson("/api/v1/inmobiliaria/propiedades/{$propiedad->id}/pausar", [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.estado.value', 'pausada');

        $this->postJson("/api/v1/inmobiliaria/propiedades/{$propiedad->id}/cerrar-operacion", [
            'estado' => 'vendida',
        ], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.estado.value', 'vendida');

        $this->assertNotNull($propiedad->refresh()->vendida_en);
        $this->assertDatabaseHas('activity_log', ['subject_type' => 'propiedad', 'subject_id' => $propiedad->id]);
    }

    /**
     * El listado del panel incluye el resumen por estado.
     */
    public function test_listado_del_panel_incluye_resumen_por_estado(): void
    {
        $usuario = $this->usuario(Role::Inmobiliaria);
        Propiedad::factory()->delPropietario($usuario->propietario())->publicada()->count(2)->create();
        Propiedad::factory()->delPropietario($usuario->propietario())->borrador()->create();

        $this->autenticar($usuario);

        $this->getJson('/api/v1/inmobiliaria/propiedades', $this->cabeceras())
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.resumen_por_estado.publicada', 2)
            ->assertJsonPath('meta.resumen_por_estado.borrador', 1);
    }

    /**
     * El perfil del panel devuelve la cuenta, el perfil y el logotipo.
     */
    public function test_perfil_del_panel_y_logotipo(): void
    {
        Storage::fake('public');

        $usuario = $this->autenticar($this->usuario(Role::Inmobiliaria));

        $this->getJson('/api/v1/inmobiliaria/perfil', $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.perfil.razon_social', $usuario->inmobiliaria->razon_social)
            ->assertJsonStructure(['data' => ['usuario', 'perfil', 'resumen_publicaciones']]);

        $this->postJson('/api/v1/inmobiliaria/perfil/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
        ], $this->cabeceras())->assertOk()->assertJsonStructure(['data' => ['url']]);

        $this->assertNotNull($usuario->inmobiliaria->refresh()->logo);

        $this->deleteJson('/api/v1/inmobiliaria/perfil/logo', [], $this->cabeceras())->assertOk();
        $this->assertNull($usuario->inmobiliaria->refresh()->logo);
    }

    /**
     * Datos mínimos de una propiedad válida.
     *
     * @return array<string, mixed>
     */
    private function datosDePropiedad(): array
    {
        return [
            'titulo' => 'Departamento luminoso en Sopocachi',
            'descripcion' => 'Departamento de dos dormitorios con vista a la ciudad, ideal para parejas o estudiantes.',
            'tipo' => 'departamento',
            'operacion' => 'venta',
            'precio' => 85000,
            'moneda' => 'USD',
            'area_total' => 95.5,
            'area_construida' => 88,
            'habitaciones' => 2,
            'banos' => 2,
            'estacionamientos' => 1,
            'direccion' => 'Av. Arce #2450',
            'ciudad' => 'La Paz',
            'estado_provincia' => 'La Paz',
            'latitud' => -16.5041,
            'longitud' => -68.1219,
        ];
    }
}
