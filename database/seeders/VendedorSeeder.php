<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Models\Vendedor;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Tres vendedores particulares de demostración
 * (contraseña común `Password123!`, ver README).
 */
class VendedorSeeder extends Seeder
{
    /**
     * Datos fijos de los vendedores demo.
     *
     * @var array<int, array<string, string>>
     */
    private const VENDEDORES = [
        [
            'email' => 'vendedor1@demo.mercadoinmueble.com',
            'nombres' => 'Luis Alberto',
            'apellidos' => 'Mendoza Vargas',
            'ciudad' => 'La Paz',
            'estado_provincia' => 'La Paz',
            'biografia' => 'Propietario de dos departamentos en alquiler en Sopocachi; respondo visitas los fines de semana.',
        ],
        [
            'email' => 'vendedor2@demo.mercadoinmueble.com',
            'nombres' => 'Patricia',
            'apellidos' => 'Gómez Fuentes',
            'ciudad' => 'Santa Cruz de la Sierra',
            'estado_provincia' => 'Santa Cruz',
            'biografia' => 'Vendo casa familiar en Equipetrol y un terreno en Urubó. Documentación al día.',
        ],
        [
            'email' => 'vendedor3@demo.mercadoinmueble.com',
            'nombres' => 'Hernán',
            'apellidos' => 'Rocha Miranda',
            'ciudad' => 'Cochabamba',
            'estado_provincia' => 'Cochabamba',
            'biografia' => 'Alquilo locales comerciales y oficinas en el centro de Cochabamba.',
        ],
    ];

    /**
     * Crea las cuentas y perfiles de vendedor demo.
     */
    public function run(): void
    {
        foreach (self::VENDEDORES as $indice => $datos) {
            $user = User::withTrashed()->firstOrNew(['email' => $datos['email']]);

            // `forceFill`: email_verified_at y deleted_at no son fillable y la
            // app evita el descarte silencioso de atributos.
            $user->forceFill([
                'name' => $datos['nombres'].' '.$datos['apellidos'],
                'password' => Hash::make(UserFactory::passwordDemo()),
                'role' => Role::Vendedor,
                'phone' => sprintf('+591 6%06d', 300000 + $indice),
                'is_active' => true,
                'email_verified_at' => now(),
                'deleted_at' => null,
            ])->save();

            $vendedor = Vendedor::withTrashed()->firstOrNew(['dni' => sprintf('%07d', 7000000 + $indice)]);

            $vendedor->forceFill([
                'user_id' => $user->getKey(),
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'telefono' => sprintf('+591 6%06d', 300000 + $indice),
                'direccion' => 'Calle '.$indice.' #'.(50 + $indice),
                'ciudad' => $datos['ciudad'],
                'estado_provincia' => $datos['estado_provincia'],
                'fecha_nacimiento' => now()->subYears(35 + $indice)->subDays(11)->toDateString(),
                'biografia' => $datos['biografia'],
                'verificado' => $indice !== 1,
                'deleted_at' => null,
            ])->save();

            $user->vincularPerfil($vendedor);
        }

        $this->command?->info(sprintf('✔ Vendedores: %d cuentas (contraseña %s).', count(self::VENDEDORES), UserFactory::passwordDemo()));
    }
}
