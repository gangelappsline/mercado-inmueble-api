<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendedor>
 */
class VendedorFactory extends Factory
{
    protected $model = Vendedor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ciudad = fake()->randomElement(array_keys(InmobiliariaFactory::CIUDADES));

        return [
            'user_id' => User::factory(),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'dni' => fake()->unique()->numerify('#######'),
            'telefono' => fake()->numerify('+591 7#######'),
            'telefono_alternativo' => fake()->optional()->numerify('+591 6#######'),
            'direccion' => fake()->streetAddress(),
            'ciudad' => $ciudad,
            'estado_provincia' => InmobiliariaFactory::CIUDADES[$ciudad],
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-22 years'),
            'biografia' => fake()->optional()->paragraph(2),
            'foto' => null,
            'verificado' => fake()->boolean(60),
        ];
    }

    /**
     * Vendedor verificado.
     */
    public function verificado(): static
    {
        return $this->state(fn (): array => ['verificado' => true]);
    }
}
