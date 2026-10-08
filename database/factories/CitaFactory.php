<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EstadoCita;
use App\Enums\TipoCita;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Inmobiliaria;
use App\Models\Propiedad;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Cita>
 */
class CitaFactory extends Factory
{
    protected $model = Cita::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fecha = Carbon::now()->addDays(fake()->numberBetween(1, 21));

        return [
            'propiedad_id' => Propiedad::factory()->publicada(),
            'cliente_id' => Cliente::factory(),
            'interes_id' => null,
            'propietario_type' => (new Inmobiliaria)->getMorphClass(),
            'propietario_id' => Inmobiliaria::factory(),
            'fecha' => $fecha->toDateString(),
            'hora' => fake()->randomElement(['09:00', '10:30', '11:00', '15:00', '16:30', '18:00']),
            'duracion_minutos' => 30,
            'tipo' => TipoCita::Visita,
            'estado' => EstadoCita::Pendiente,
            'lugar' => fake()->streetAddress(),
            'enlace_virtual' => null,
            'notas' => fake()->optional()->sentence(8),
        ];
    }

    public function confirmada(): static
    {
        return $this->state(fn (): array => ['estado' => EstadoCita::Confirmada, 'confirmada_en' => now()]);
    }

    public function cancelada(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoCita::Cancelada,
            'cancelada_en' => now(),
            'motivo_cancelacion' => 'El cliente no podrá asistir.',
        ]);
    }

    public function completada(): static
    {
        return $this->state(fn (): array => [
            'estado' => EstadoCita::Completada,
            'completada_en' => now(),
            'fecha' => Carbon::now()->subDays(fake()->numberBetween(1, 15))->toDateString(),
        ]);
    }

    public function virtual(): static
    {
        return $this->state(fn (): array => [
            'tipo' => TipoCita::Virtual,
            'enlace_virtual' => 'https://meet.mercadoinmueble.test/'.fake()->uuid(),
            'lugar' => null,
        ]);
    }

    public function llamada(): static
    {
        return $this->state(fn (): array => [
            'tipo' => TipoCita::Llamada,
            'lugar' => null,
        ]);
    }

    public function enFecha(string $fecha, string $hora = '10:00'): static
    {
        return $this->state(fn (): array => ['fecha' => $fecha, 'hora' => $hora]);
    }
}
