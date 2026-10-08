<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EstadoPropiedad;
use App\Models\Amenidad;
use App\Models\Inmobiliaria;
use App\Models\Propiedad;
use App\Models\PropiedadFoto;
use App\Models\PropiedadVideo;
use App\Models\Vendedor;
use Database\Factories\PropiedadFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Publicaciones de demostración: 26 propiedades repartidas entre las cinco
 * inmobiliarias y los tres vendedores, con fotos, videos, amenidades y
 * contadores de actividad.
 *
 * Los archivos de medios son rutas de ejemplo (no hay binarios en el
 * repositorio); los nombres siguen la convención del MediaService real.
 */
class PropiedadSeeder extends Seeder
{
    /**
     * Plan de publicaciones por inmobiliaria: [estado, destacada].
     *
     * @var array<int, array{0: EstadoPropiedad, 1: bool}>
     */
    private const PLAN_INMOBILIARIA = [
        [EstadoPropiedad::Publicada, true],
        [EstadoPropiedad::Publicada, true],
        [EstadoPropiedad::Publicada, false],
        [EstadoPropiedad::Publicada, false],
    ];

    /**
     * Crea las propiedades demo con todos sus medios.
     */
    public function run(): void
    {
        /** @var array<int, Model> $inmobiliarias */
        $inmobiliarias = Inmobiliaria::orderBy('id')->get()->all();
        /** @var array<int, Model> $vendedores */
        $vendedores = Vendedor::orderBy('id')->get()->all();

        $total = 0;

        foreach ($inmobiliarias as $indice => $inmobiliaria) {
            foreach (self::PLAN_INMOBILIARIA as $posicion => [$estado, $destacada]) {
                $total += $this->crearPropiedad(
                    propietario: $inmobiliaria,
                    estado: $estado,
                    destacada: $destacada,
                    posicion: ($indice * 4) + $posicion,
                );
            }
        }

        // Los vendedores publican menos: dos propiedades cada uno.
        foreach ($vendedores as $indice => $vendedor) {
            $total += $this->crearPropiedad(
                propietario: $vendedor,
                estado: EstadoPropiedad::Publicada,
                destacada: false,
                posicion: ($indice * 2) + 21,
            );
            $total += $this->crearPropiedad(
                propietario: $vendedor,
                estado: $indice === 2 ? EstadoPropiedad::Pausada : EstadoPropiedad::Publicada,
                destacada: false,
                posicion: ($indice * 2) + 22,
            );
        }

        // Estado de ejemplo para el flujo completo: un borrador y una operación cerrada.
        $total += $this->crearPropiedad($inmobiliarias[2] ?? $vendedores[0], EstadoPropiedad::Borrador, false, 90);
        $total += $this->crearPropiedad($inmobiliarias[4] ?? $vendedores[0], EstadoPropiedad::Vendida, false, 91);


        $this->command?->info(sprintf('✔ Propiedades: %d publicaciones con fotos, videos y amenidades.', $total));
    }

    /**
     * Crea una propiedad con fotos, video opcional, amenidades y contadores.
     *
     * @param  Model  $propietario  Inmobiliaria o Vendedor
     * @return int 1 si se creó correctamente
     */
    private function crearPropiedad(
        Model $propietario,
        EstadoPropiedad $estado,
        bool $destacada,
        int $posicion,
    ): int {
        $ciudades = array_keys(PropiedadFactory::CIUDADES);

        $factory = Propiedad::factory()->delPropietario($propietario);

        if ($posicion % 2 === 0) {
            $factory = $factory->enCiudad($ciudades[$posicion % count($ciudades)]);
        }

        $factory = match ($estado) {
            EstadoPropiedad::Publicada => $factory->publicada(),
            EstadoPropiedad::Borrador => $factory->borrador(),
            EstadoPropiedad::Pausada => $factory->pausada(),
            EstadoPropiedad::Vendida => $factory->vendida(),
            default => $factory,
        };

        if ($destacada) {
            $factory = $factory->destacada();
        }

        $propiedad = $factory->create();

        // Fotos: entre 3 y 6, la primera es la principal.
        $cantidadFotos = 3 + ($posicion % 4);
        for ($i = 0; $i < $cantidadFotos; $i++) {
            PropiedadFoto::factory()
                ->when($i === 0, fn ($f) => $f->principal())
                ->create([
                    'propiedad_id' => $propiedad->getKey(),
                    'ruta' => sprintf('propiedades/%s/foto-%d.jpg', $propiedad->codigo, $i + 1),
                    'thumbnail' => sprintf('propiedades/%s/thumbs/foto-%d.jpg', $propiedad->codigo, $i + 1),
                    'orden' => $i,
                ]);
        }

        // Video cada tres publicaciones.
        if ($posicion % 3 === 0) {
            PropiedadVideo::factory()->create([
                'propiedad_id' => $propiedad->getKey(),
                'ruta' => sprintf('videos/%s/tour.mp4', $propiedad->codigo),
                'thumbnail' => sprintf('propiedades/%s/thumbs/video.jpg', $propiedad->codigo),
            ]);
        }

        // Amenidades del catálogo (sin repetir).
        $amenidadIds = Amenidad::query()
            ->activas()
            ->inRandomOrder()
            ->limit(4 + ($posicion % 6))
            ->pluck('id')
            ->all();

        if ($amenidadIds !== []) {
            $propiedad->amenidades()->sync($amenidadIds);
        }

        // Contadores de actividad coherentes con el estado de la publicación.
        $vistas = $estado === EstadoPropiedad::Publicada ? random_int(45, 980) : random_int(0, 40);

        $propiedad->forceFill([
            'vistas_count' => $vistas,
            'contactos_count' => (int) round($vistas * 0.12),
            'favoritos_count' => (int) round($vistas * 0.07),
            'citas_count' => (int) round($vistas * 0.03),
        ])->saveQuietly();

        return 1;
    }
}
