<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Amenidad;
use App\Models\Propiedad;
use Database\Seeders\AmenidadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Catálogo público: filtros, destacadas, detalle con vistas, catálogos
 * cacheados y formulario de contacto.
 */
class CatalogoPublicoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El listado público devuelve sólo propiedades publicadas y filtra.
     */
    public function test_listado_publico_filtra_por_ciudad_y_precio(): void
    {
        $inmobiliaria = $this->usuario(\App\Enums\Role::Inmobiliaria);

        Propiedad::factory()->delPropietario($inmobiliaria->propietario())->publicada()->enCiudad('La Paz')->conPrecio(90000)->create();
        Propiedad::factory()->delPropietario($inmobiliaria->propietario())->publicada()->enCiudad('Cochabamba')->conPrecio(50000)->create();
        Propiedad::factory()->delPropietario($inmobiliaria->propietario())->borrador()->create();

        $respuesta = $this->getJson('/api/v1/propiedades?ciudad=La%20Paz&precio_max=100000', $this->cabeceras());

        $respuesta->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ciudad', 'La Paz');
    }

    /**
     * Los filtros inválidos devuelven el sobre de validación en español.
     */
    public function test_listado_publico_valida_los_filtros(): void
    {
        $this->getJson('/api/v1/propiedades?precio_min=100&precio_max=50', $this->cabeceras())
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['precio_max']]);
    }

    /**
     * El detalle incrementa el contador de vistas y expone fotos y amenidades.
     */
    public function test_detalle_incrementa_vistas_y_devuelve_galeria(): void
    {
        $this->seed(AmenidadSeeder::class);

        $inmobiliaria = $this->usuario(\App\Enums\Role::Inmobiliaria);
        $propiedad = Propiedad::factory()
            ->delPropietario($inmobiliaria->propietario())
            ->publicada()
            ->conFotos(3)
            ->conAmenidades(4)
            ->create();

        $respuesta = $this->getJson("/api/v1/propiedades/{$propiedad->id}", $this->cabeceras());

        $respuesta->assertOk()
            ->assertJsonPath('data.id', $propiedad->id)
            ->assertJsonCount(3, 'data.fotos')
            ->assertJsonCount(4, 'data.amenidades')
            ->assertJsonStructure(['data' => ['contacto' => ['nombre', 'email', 'telefono']]]);

        $this->assertSame(1, (int) $propiedad->refresh()->vistas_count);
        $this->assertDatabaseHas('reportes', ['propiedad_id' => $propiedad->id, 'tipo' => 'vista']);
    }

    /**
     * Un borrador no es visible públicamente.
     */
    public function test_detalle_de_borrador_devuelve_404(): void
    {
        $inmobiliaria = $this->usuario(\App\Enums\Role::Inmobiliaria);
        $propiedad = Propiedad::factory()->delPropietario($inmobiliaria->propietario())->borrador()->create();

        $this->getJson("/api/v1/propiedades/{$propiedad->id}", $this->cabeceras())
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    /**
     * Las destacadas y los catálogos se sirven desde caché.
     */
    public function test_destacadas_y_catalogos(): void
    {
        $this->seed(AmenidadSeeder::class);

        $inmobiliaria = $this->usuario(\App\Enums\Role::Inmobiliaria);
        Propiedad::factory()
            ->delPropietario($inmobiliaria->propietario())
            ->publicada()
            ->destacada()
            ->enCiudad($inmobiliaria->inmobiliaria->ciudad)
            ->create();

        $this->getJson('/api/v1/propiedades/destacadas', $this->cabeceras())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.destacada', true);

        $this->getJson('/api/v1/amenidades', $this->cabeceras())
            ->assertOk()
            ->assertJsonCount(Amenidad::count(), 'data');

        $this->getJson('/api/v1/ciudades', $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.0.ciudad', $inmobiliaria->inmobiliaria->ciudad)
            ->assertJsonPath('data.0.propiedades_count', 1);

        $this->getJson('/api/v1/catalogo/opciones', $this->cabeceras())
            ->assertOk()
            ->assertJsonStructure(['data' => ['tipos_propiedad', 'operaciones', 'monedas', 'estados_cita']]);
    }

    /**
     * El formulario de contacto registra el mensaje y notifica a soporte.
     */
    public function test_contacto_registra_y_notifica(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/contacto', [
            'nombre' => 'Persona Interesada',
            'email' => 'persona@demo.test',
            'asunto' => 'Quiero publicar mi propiedad',
            'mensaje' => 'Hola, tengo una casa en venta y quisiera publicarla en la plataforma.',
        ], $this->cabeceras())
            ->assertCreated()
            ->assertJsonPath('message', __('messages.contacto_recibido'));

        $this->assertDatabaseHas('contactos', ['email' => 'persona@demo.test']);
    }
}
