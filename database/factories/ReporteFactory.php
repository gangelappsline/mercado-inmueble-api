<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoReporte;
use App\Models\Inmobiliaria;
use App\Models\Propiedad;
use App\Models\Reporte;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reporte>
 */
class ReporteFactory extends Factory
{
    protected $model = Reporte::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'propietario_type' => (new Inmobiliaria)->getMorphClass(),
            'propietario_id' => Inmobiliaria::factory(),
            'propiedad_id' => Propiedad::factory()->publicada(),
            'cliente_id' => null,
            'tipo' => TipoReporte::Vista,
            'fecha' => now()->subDays(fake()->numberBetween(0, 30))->toDateString(),
            'cantidad' => fake()->numberBetween(1, 120),
            'valor' => null,
            'metadata' => null,
        ];
    }

    public function deTipo(TipoReporte $tipo): static
    {
        return $this->state(fn (): array => ['tipo' => $tipo]);
    }

    public function enFecha(string $fecha): static
    {
        return $this->state(fn (): array => ['fecha' => $fecha]);
    }
}
