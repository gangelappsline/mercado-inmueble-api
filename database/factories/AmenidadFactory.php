<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CategoriaAmenidad;
use App\Models\Amenidad;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Amenidad>
 */
class AmenidadFactory extends Factory
{
    protected $model = Amenidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = Str::title(fake()->unique()->words(2, true));

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'icono' => fake()->randomElement(['pool', 'gym', 'tree', 'shield', 'wifi', 'car']),
            'categoria' => fake()->randomElement(CategoriaAmenidad::cases()),
            'activa' => true,
            'orden' => fake()->numberBetween(0, 50),
        ];
    }

    /**
     * Amenidad fuera de catálogo (no se ofrece al publicar).
     */
    public function inactiva(): static
    {
        return $this->state(fn (): array => ['activa' => false]);
    }
}
