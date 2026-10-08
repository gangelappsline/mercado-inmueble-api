<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Hilo;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mensaje>
 */
class MensajeFactory extends Factory
{
    protected $model = Mensaje::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hilo_id' => Hilo::factory(),
            'user_id' => User::factory(),
            'rol_autor' => Role::Cliente,
            'cuerpo' => fake()->paragraph(2),
            'adjunto' => null,
            'leido' => false,
            'leido_en' => null,
        ];
    }

    /**
     * Mensaje escrito por el cliente.
     */
    public function delCliente(?User $autor = null): static
    {
        return $this->state(fn (): array => [
            'rol_autor' => Role::Cliente,
            'user_id' => $autor?->getKey() ?? User::factory(),
        ]);
    }

    /**
     * Mensaje escrito por el anunciante.
     */
    public function delPropietario(?User $autor = null): static
    {
        return $this->state(fn (): array => [
            'rol_autor' => Role::Inmobiliaria,
            'user_id' => $autor?->getKey() ?? User::factory(),
        ]);
    }
}
