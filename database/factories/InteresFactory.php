<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoInteres;
use App\Models\Cliente;
use App\Models\Interes;
use App\Models\Propiedad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interes>
 */
class InteresFactory extends Factory
{
    protected $model = Interes::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'propiedad_id' => Propiedad::factory()->publicada(),
            'cliente_id' => Cliente::factory(),
            'mensaje' => fake()->randomElement([
                'Hola, me interesa la propiedad. ¿Sigue disponible?',
                '¿Es posible visitarla este fin de semana?',
                '¿El precio es negociable? Tengo financiamiento aprobado.',
                'Buenas tardes, quisiera conocer los gastos comunes.',
                '¿Aceptan crédito hipotecario bancario?',
            ]),
            'estado' => EstadoInteres::Nuevo,
            'origen' => fake()->randomElement(['web', 'app', 'telefono', 'whatsapp']),
            'contactado' => false,
        ];
    }

    /**
     * Interés ya atendido por el anunciante.
     */
    public function atendido(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoInteres::Atendido,
            'contactado' => true,
            'atendido_en' => now()->subDays(fake()->numberBetween(1, 10)),
        ]);
    }

    /**
     * Interés convertido en operación.
     */
    public function cerrado(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoInteres::Cerrado,
            'contactado' => true,
            'atendido_en' => now()->subDays(fake()->numberBetween(5, 20)),
            'cerrado_en' => now()->subDays(fake()->numberBetween(1, 4)),
        ]);
    }
}
