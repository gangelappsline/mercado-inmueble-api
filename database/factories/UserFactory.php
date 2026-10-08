<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Cliente;
use App\Models\Inmobiliaria;
use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Contraseña reutilizada por los datos de demostración.
     */
    public const PASSWORD_DEMO = 'Password123!';

    /**
     * Contraseña de los datos de demostración (para seeders y README).
     */
    public static function passwordDemo(): string
    {
        return self::PASSWORD_DEMO;
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make(self::PASSWORD_DEMO),
            'role' => Role::Cliente,
            'phone' => fake()->numerify('+591 7#######'),
            'avatar' => null,
            'is_active' => true,
            'remember_token' => Str::random(10),
            'last_login_at' => null,
        ];
    }

    /**
     * Usuario sin correo verificado.
     */
    public function noVerificado(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    /**
     * Usuario desactivado por un administrador.
     */
    public function inactivo(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * Crea el usuario con rol `administrador` (agente de la plataforma).
     *
     * Las cuentas administrativas no tienen perfil extendido: `perfil_type` y
     * `perfil_id` quedan en null.
     */
    public function administrador(): static
    {
        return $this->state(fn (): array => ['role' => Role::Administrador]);
    }

    /**
     * Crea el usuario con rol `inmobiliaria` y su perfil extendido.
     */
    public function inmobiliaria(): static
    {
        return $this->state(fn (): array => ['role' => Role::Inmobiliaria])
            ->afterCreating(static function (User $user): void {
                $user->vincularPerfil(Inmobiliaria::factory()->create(['user_id' => $user->getKey()]));
            });
    }

    /**
     * Crea el usuario con rol `vendedor` y su perfil extendido.
     */
    public function vendedor(): static
    {
        return $this->state(fn (): array => ['role' => Role::Vendedor])
            ->afterCreating(static function (User $user): void {
                $user->vincularPerfil(Vendedor::factory()->create(['user_id' => $user->getKey()]));
            });
    }

    /**
     * Crea el usuario con rol `cliente` y su perfil extendido.
     */
    public function cliente(): static
    {
        return $this->state(fn (): array => ['role' => Role::Cliente])
            ->afterCreating(static function (User $user): void {
                $user->vincularPerfil(Cliente::factory()->create(['user_id' => $user->getKey()]));
            });
    }
}
