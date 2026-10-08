<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CategoriaAmenidad;
use App\Models\Amenidad;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catálogo base de amenidades usado por los filtros del catálogo público.
 * Es idempotente: se puede re-ejecutar sin duplicar registros.
 */
class AmenidadSeeder extends Seeder
{
    /**
     * Catálogo por categoría: nombre => icono.
     *
     * @var array<string, array<string, string>>
     */
    private const CATALOGO = [
        CategoriaAmenidad::Interior->value => [
            'Cocina equipada' => 'kitchen',
            'Amoblado' => 'sofa',
            'Closets empotrados' => 'wardrobe',
            'Piso de madera' => 'floor',
            'Aire acondicionado' => 'air-conditioner',
            'Calefacción' => 'heater',
            'Chimenea' => 'fireplace',
            'Baño con tina' => 'bathtub',
        ],
        CategoriaAmenidad::Exterior->value => [
            'Jardín' => 'tree',
            'Terraza' => 'terrace',
            'Balcón' => 'balcony',
            'Patio' => 'patio',
            'Piscina privada' => 'pool',
            'Asador / parrilla' => 'grill',
            'Vista panorámica' => 'mountain',
        ],
        CategoriaAmenidad::Comunidad->value => [
            'Salón de eventos' => 'party',
            'Gimnasio' => 'gym',
            'Área de juegos infantiles' => 'playground',
            'Cancha deportiva' => 'sports',
            'Coworking' => 'coworking',
            'Piscina común' => 'pool',
            'Salón de usos múltiples' => 'meeting',
        ],
        CategoriaAmenidad::Seguridad->value => [
            'Portería 24 horas' => 'shield',
            'Vigilancia privada' => 'guard',
            'Circuito cerrado de TV' => 'camera',
            'Acceso controlado' => 'access',
            'Alarma de intrusión' => 'alarm',
            'Cerca perimetral' => 'fence',
        ],
        CategoriaAmenidad::Servicios->value => [
            'Agua potable' => 'water',
            'Energía eléctrica' => 'energy',
            'Gas domiciliario' => 'gas',
            'Alcantarillado' => 'sewer',
            'Internet de alta velocidad' => 'wifi',
            'Ascensor' => 'elevator',
            'Estacionamiento de visitantes' => 'car',
            'Generador de emergencia' => 'generator',
        ],
    ];

    /**
     * Inserta/actualiza el catálogo de amenidades.
     */
    public function run(): void
    {
        $orden = 1;

        foreach (self::CATALOGO as $categoria => $amenidades) {
            foreach ($amenidades as $nombre => $icono) {
                $amenidad = Amenidad::withTrashed()->firstOrNew(['slug' => Str::slug($nombre)]);

                // `forceFill`: `deleted_at` no es un atributo fillable y la app
                // evita el descarte silencioso de atributos.
                $amenidad->forceFill([
                    'nombre' => $nombre,
                    'icono' => $icono,
                    'categoria' => CategoriaAmenidad::from($categoria),
                    'activa' => true,
                    'orden' => $orden,
                    'deleted_at' => null,
                ])->save();

                $orden++;
            }
        }

        Amenidad::limpiarCache();

        $this->command?->info(sprintf('✔ Amenidades: %d registros en catálogo.', Amenidad::count()));
    }
}
