<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Propiedad;
use App\Models\PropiedadVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropiedadVideo>
 */
class PropiedadVideoFactory extends Factory
{
    protected $model = PropiedadVideo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'propiedad_id' => Propiedad::factory(),
            'ruta' => sprintf('videos/%s.mp4', fake()->uuid()),
            'disk' => 'private',
            'nombre_original' => 'tour-'.fake()->word().'.mp4',
            'mime' => 'video/mp4',
            'tamanio' => fake()->numberBetween(4_000_000, 90_000_000),
            'duracion' => fake()->numberBetween(20, 240),
            'thumbnail' => sprintf('propiedades/demo/thumbs/video-%s.jpg', fake()->uuid()),
        ];
    }
}
