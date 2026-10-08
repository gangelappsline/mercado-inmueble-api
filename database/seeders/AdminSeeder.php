<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cuentas del equipo de administración (rol `administrador`).
 *
 * Son los agentes que gestionan las inmobiliarias, los vendedores, los clientes
 * y la moderación del catálogo desde `/api/v1/admin/*`. Comparten la contraseña
 * de demostración `Password123!` y **no** tienen perfil extendido.
 *
 * En producción las cuentas administrativas se crean con
 * `php artisan mercado:crear-admin {correo}` (nunca por el registro público).
 */
class AdminSeeder extends Seeder
{
    /**
     * Agentes de demostración.
     *
     * @var array<int, array<string, string>>
     */
    private const ADMINISTRADORES = [
        [
            'email' => 'admin@demo.mercadoinmueble.com',
            'name' => 'Valeria Montaño',
            'phone' => '+591 70000000',
        ],
        [
            'email' => 'agente1@demo.mercadoinmueble.com',
            'name' => 'Iván Torrico',
            'phone' => '+591 70000001',
        ],
    ];

    /**
     * Crea (o actualiza) las cuentas administrativas demo.
     */
    public function run(): void
    {
        foreach (self::ADMINISTRADORES as $datos) {
            $user = User::withTrashed()->firstOrNew(['email' => $datos['email']]);

            // `forceFill`: email_verified_at y deleted_at no son fillable y la
            // app evita el descarte silencioso de atributos.
            $user->forceFill([
                'name' => $datos['name'],
                'password' => Hash::make(UserFactory::passwordDemo()),
                'role' => Role::Administrador,
                'phone' => $datos['phone'],
                'is_active' => true,
                'email_verified_at' => now(),
                'deleted_at' => null,
            ])->save();
        }

        $this->command?->info(sprintf(
            '✔ Administradores: %d cuentas (contraseña %s).',
            count(self::ADMINISTRADORES),
            UserFactory::passwordDemo(),
        ));
    }
}
