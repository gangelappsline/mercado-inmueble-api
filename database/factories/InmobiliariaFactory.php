<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Inmobiliaria;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Inmobiliaria>
 */
class InmobiliariaFactory extends Factory
{
    protected $model = Inmobiliaria::class;

    /**
     * Ciudades de demostración (con su departamento).
     *
     * @var array<string, string>
     */
    public const CIUDADES = [
        'La Paz' => 'La Paz',
        'Santa Cruz de la Sierra' => 'Santa Cruz',
        'Cochabamba' => 'Cochabamba',
        'El Alto' => 'La Paz',
        'Sucre' => 'Chuquisaca',
        'Tarija' => 'Tarija',
        'Oruro' => 'Oruro',
        'Trinidad' => 'Beni',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ciudad = fake()->randomElement(array_keys(self::CIUDADES));
        $razonSocial = sprintf('%s %s S.R.L.', fake()->lastName(), fake()->randomElement(['Propiedades', 'Inmobiliaria', 'Bienes Raíces', 'Casa & Terreno']));

        return [
            'user_id' => User::factory(),
            'razon_social' => $razonSocial,
            'nombre_comercial' => Str::title(fake()->words(2, true)),
            'ruc' => fake()->unique()->numerify('##########'),
            'logo' => null,
            'direccion' => fake()->streetAddress(),
            'telefono' => fake()->numerify('+591 3#######'),
            'telefono_alternativo' => fake()->optional()->numerify('+591 7#######'),
            'web' => fake()->optional()->url(),
            'descripcion' => fake()->paragraph(3),
            'ciudad' => $ciudad,
            'estado_provincia' => self::CIUDADES[$ciudad],
            'pais' => 'Bolivia',
            'verificado' => fake()->boolean(70),
        ];
    }

    /**
     * Inmobiliaria verificada por la plataforma.
     */
    public function verificada(): static
    {
        return $this->state(fn (): array => ['verificado' => true]);
    }

    /**
     * Inmobiliaria en una ciudad concreta.
     */
    public function enCiudad(string $ciudad): static
    {
        return $this->state(fn (): array => [
            'ciudad' => $ciudad,
            'estado_provincia' => self::CIUDADES[$ciudad] ?? $ciudad,
        ]);
    }
}
