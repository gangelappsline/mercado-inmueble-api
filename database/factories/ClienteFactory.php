<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Moneda;
use App\Enums\TipoPropiedad;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minimo = fake()->numberBetween(30_000, 150_000);
        $moneda = fake()->randomElement([Moneda::Bob, Moneda::Usd]);

        return [
            'user_id' => User::factory(),
            'telefono' => fake()->numerify('+591 7#######'),
            'telefono_alternativo' => fake()->optional()->numerify('+591 6#######'),
            'presupuesto_min' => $minimo,
            'presupuesto_max' => $minimo + fake()->numberBetween(20_000, 300_000),
            'moneda' => $moneda,
            'tipo_propiedad_interes' => fake()->randomElement(TipoPropiedad::cases()),
            'ciudad_interes' => fake()->randomElement(array_keys(InmobiliariaFactory::CIUDADES)),
            'habitaciones_min' => fake()->numberBetween(1, 4),
            'preferencias' => [
                'amoblado' => fake()->boolean(30),
                'acepta_mascotas' => fake()->boolean(40),
            ],
            'acepta_terminos' => true,
            'recibe_novedades' => fake()->boolean(70),
        ];
    }

    /**
     * Cliente sin presupuesto definido (sólo explora).
     */
    public function sinPresupuesto(): static
    {
        return $this->state(fn (): array => [
            'presupuesto_min' => null,
            'presupuesto_max' => null,
            'habitaciones_min' => null,
            'tipo_propiedad_interes' => null,
        ]);
    }
}
