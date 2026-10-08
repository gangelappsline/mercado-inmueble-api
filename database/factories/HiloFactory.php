<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hilo;
use App\Models\Interes;
use App\Models\Propiedad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hilo>
 */
class HiloFactory extends Factory
{
    protected $model = Hilo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interes_id' => Interes::factory(),
            'asunto' => 'Consulta sobre '.fake()->words(3, true),
            'ultimo_mensaje_en' => null,
            'no_leidos_cliente' => 0,
            'no_leidos_propietario' => 0,
            'total_mensajes' => 0,
            'cerrado' => false,
        ];
    }

    /**
     * Hilo con un asunto derivado de la propiedad del interés.
     */
    public function paraInteres(Interes $interes, ?Propiedad $propiedad = null): static
    {
        $propiedad ??= $interes->propiedad;

        return $this->state(fn (): array => [
            'interes_id' => $interes->getKey(),
            'asunto' => $propiedad !== null ? Hilo::asuntoPara($propiedad) : 'Consulta',
        ]);
    }
}
