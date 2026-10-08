<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TipoReporte;
use App\Models\Propiedad;
use App\Models\Reporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Favoritos del cliente y reportes del panel de anunciante.
 */
class FavoritoYReporteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guardar, duplicar, listar, anotar y quitar un favorito.
     */
    public function test_gestion_de_favoritos(): void
    {
        $propiedad = $this->propiedadPublicada();
        $this->autenticar($this->usuario(Role::Cliente));

        $this->postJson('/api/v1/cliente/favoritos', [
            'propiedad_id' => $propiedad->id,
            'nota' => 'Comparar con la casa de Calacoto.',
        ], $this->cabeceras())
            ->assertCreated()
            ->assertJsonPath('data.total_favoritos', 1);

        $this->postJson('/api/v1/cliente/favoritos', [
            'propiedad_id' => $propiedad->id,
        ], $this->cabeceras())
            ->assertStatus(409)
            ->assertJsonPath('message', __('messages.favorito_duplicado'));

        $this->getJson('/api/v1/cliente/favoritos', $this->cabeceras())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $propiedad->id);

        $this->patchJson("/api/v1/cliente/favoritos/{$propiedad->id}", [
            'nota' => 'Llamar el lunes.',
        ], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.nota', 'Llamar el lunes.');

        $this->deleteJson("/api/v1/cliente/favoritos/{$propiedad->id}", [], $this->cabeceras())->assertOk();

        $this->assertDatabaseCount('favoritos', 0);
        $this->assertSame(0, (int) $propiedad->refresh()->favoritos_count);
    }

    /**
     * Un favorito inexistente no puede eliminarse (404).
     */
    public function test_quitar_un_favorito_inexistente_devuelve_404(): void
    {
        $propiedad = $this->propiedadPublicada();
        $this->autenticar($this->usuario(Role::Cliente));

        $this->deleteJson("/api/v1/cliente/favoritos/{$propiedad->id}", [], $this->cabeceras())
            ->assertNotFound()
            ->assertJsonPath('message', __('messages.favorito_no_existe'));
    }

    /**
     * Los reportes del panel devuelven los agregados del periodo.
     */
    public function test_reportes_del_panel(): void
    {
        $usuario = $this->usuario(Role::Inmobiliaria);
        $propiedad = Propiedad::factory()->delPropietario($usuario->propietario())->publicada()->create();

        $this->sembrarReportes($usuario->propietario(), $propiedad);

        $this->autenticar($usuario);

        $this->getJson('/api/v1/inmobiliaria/reportes/resumen', $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/inmobiliaria/reportes/propiedades-mas-vistas?limite=5', $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.0.codigo', $propiedad->codigo)
            ->assertJsonStructure(['data' => [['id', 'titulo', 'vistas_periodo']]]);

        $this->getJson('/api/v1/inmobiliaria/reportes/conversion', $this->cabeceras())
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson('/api/v1/inmobiliaria/reportes/ingresos', $this->cabeceras())
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    /**
     * La exportación CSV devuelve un archivo descargable con encabezados.
     */
    public function test_exportar_reporte_en_csv(): void
    {
        $usuario = $this->usuario(Role::Vendedor);
        $propiedad = Propiedad::factory()->delPropietario($usuario->propietario())->publicada()->create();

        $this->sembrarReportes($usuario->propietario(), $propiedad);

        $this->autenticar($usuario);

        $respuesta = $this->get('/api/v1/vendedor/reportes/exportar?reporte=propiedades-mas-vistas&formato=csv', $this->cabeceras());

        $respuesta->assertOk();

        $this->assertStringContainsString('.csv', (string) $respuesta->headers->get('content-disposition'));
        $this->assertStringContainsString($propiedad->codigo, (string) $respuesta->getContent());
    }

    /**
     * Un cliente no accede a los reportes del panel.
     */
    public function test_un_cliente_no_accede_a_los_reportes(): void
    {
        $this->autenticar($this->usuario(Role::Cliente));

        $this->getJson('/api/v1/vendedor/reportes/resumen', $this->cabeceras())->assertForbidden();
    }

    /**
     * Un vendedor no ve las métricas de una inmobiliaria.
     */
    public function test_aislamiento_de_reportes_entre_anunciantes(): void
    {
        $inmobiliaria = $this->usuario(Role::Inmobiliaria);
        $propiedad = Propiedad::factory()->delPropietario($inmobiliaria->propietario())->publicada()->create();
        $this->sembrarReportes($inmobiliaria->propietario(), $propiedad);

        $this->autenticar($this->usuario(Role::Vendedor));

        $this->getJson('/api/v1/vendedor/reportes/propiedades-mas-vistas', $this->cabeceras())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * Propiedad publicada con fotos y su anunciante.
     */
    private function propiedadPublicada(): Propiedad
    {
        $anunciante = $this->usuario(Role::Vendedor);

        return Propiedad::factory()
            ->delPropietario($anunciante->propietario())
            ->publicada()
            ->conFotos(2)
            ->create();
    }

    /**
     * Genera las métricas diarias de la propiedad para el periodo.
     */
    private function sembrarReportes(\Illuminate\Database\Eloquent\Model $propietario, Propiedad $propiedad): void
    {
        for ($dia = 0; $dia < 5; $dia++) {
            $fecha = now()->subDays($dia)->toDateString();

            Reporte::factory()->deTipo(TipoReporte::Vista)->enFecha($fecha)->create([
                'propietario_type' => $propietario->getMorphClass(),
                'propietario_id' => $propietario->getKey(),
                'propiedad_id' => $propiedad->id,
                'cantidad' => 10 + $dia,
            ]);

            Reporte::factory()->deTipo(TipoReporte::Contacto)->enFecha($fecha)->create([
                'propietario_type' => $propietario->getMorphClass(),
                'propietario_id' => $propietario->getKey(),
                'propiedad_id' => $propiedad->id,
                'cantidad' => 2,
            ]);
        }

        Reporte::factory()->deTipo(TipoReporte::Conversion)->enFecha(now()->toDateString())->create([
            'propietario_type' => $propietario->getMorphClass(),
            'propietario_id' => $propietario->getKey(),
            'propiedad_id' => $propiedad->id,
            'cantidad' => 1,
            'valor' => (float) $propiedad->precio,
        ]);
    }
}
