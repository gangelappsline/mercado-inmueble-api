<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Favorito;
use App\Models\Propiedad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorito>
 */
class FavoritoFactory extends Factory
{
    protected $model = Favorito::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'propiedad_id' => Propiedad::factory(),
            'nota' => fake()->optional()->sentence(6),
        ];
    }
}
