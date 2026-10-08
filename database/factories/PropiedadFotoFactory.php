<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Propiedad;
use App\Models\PropiedadFoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropiedadFoto>
 */
class PropiedadFotoFactory extends Factory
{
    protected $model = PropiedadFoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ancho = fake()->randomElement([1200, 1600, 1920]);
        $alto = (int) round($ancho * fake()->randomFloat(2, 0.6, 0.8));

        return [
            'propiedad_id' => Propiedad::factory(),
            'ruta' => sprintf('propiedades/demo/%s.jpg', fake()->uuid()),
            'disk' => 'public',
            'nombre_original' => fake()->word().'.jpg',
            'mime' => 'image/jpeg',
            'tamanio' => fake()->numberBetween(120_000, 3_500_000),
            'ancho' => $ancho,
            'alto' => $alto,
            'thumbnail' => sprintf('propiedades/demo/thumbs/%s.jpg', fake()->uuid()),
            'orden' => 0,
            'es_principal' => false,
        ];
    }

    /**
     * Marca esta foto como principal.
     */
    public function principal(): static
    {
        return $this->state(fn (): array => ['es_principal' => true, 'orden' => 0]);
    }
}
