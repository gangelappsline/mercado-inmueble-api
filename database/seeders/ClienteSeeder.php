<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Moneda;
use App\Enums\Role;
use App\Enums\TipoPropiedad;
use App\Models\Cliente;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Diez clientes de demostración con preferencias de búsqueda variadas
 * (contraseña común `Password123!`, ver README).
 */
class ClienteSeeder extends Seeder
{
    /**
     * Datos fijos de los clientes demo.
     *
     * @var array<int, array<string, mixed>>
     */
    private const CLIENTES = [
        ['name' => 'Ana Belén Torres', 'ciudad' => 'La Paz', 'tipo' => TipoPropiedad::Departamento, 'min' => 45000, 'max' => 95000, 'habitaciones' => 2],
        ['name' => 'Carlos Ibáñez', 'ciudad' => 'Santa Cruz de la Sierra', 'tipo' => TipoPropiedad::Casa, 'min' => 80000, 'max' => 180000, 'habitaciones' => 3],
        ['name' => 'Daniela Paredes', 'ciudad' => 'Cochabamba', 'tipo' => TipoPropiedad::Departamento, 'min' => 38000, 'max' => 72000, 'habitaciones' => 1],
        ['name' => 'Eduardo Salinas', 'ciudad' => 'La Paz', 'tipo' => TipoPropiedad::Oficina, 'min' => 60000, 'max' => 140000, 'habitaciones' => 0],
        ['name' => 'Fernanda Rojas', 'ciudad' => 'El Alto', 'tipo' => TipoPropiedad::Casa, 'min' => 40000, 'max' => 85000, 'habitaciones' => 3],
        ['name' => 'Gabriel Núñez', 'ciudad' => 'Sucre', 'tipo' => TipoPropiedad::Terreno, 'min' => 25000, 'max' => 60000, 'habitaciones' => 0],
        ['name' => 'Helena Céspedes', 'ciudad' => 'Santa Cruz de la Sierra', 'tipo' => TipoPropiedad::Departamento, 'min' => 55000, 'max' => 110000, 'habitaciones' => 2],
        ['name' => 'Iván Mostajo', 'ciudad' => 'Tarija', 'tipo' => TipoPropiedad::Casa, 'min' => 45000, 'max' => 90000, 'habitaciones' => 4],
        ['name' => 'Jimena Arce', 'ciudad' => 'Cochabamba', 'tipo' => TipoPropiedad::LocalComercial, 'min' => 70000, 'max' => 160000, 'habitaciones' => 0],
        ['name' => 'Kevin Villca', 'ciudad' => 'Oruro', 'tipo' => TipoPropiedad::Departamento, 'min' => 30000, 'max' => 65000, 'habitaciones' => 2],
    ];

    /**
     * Crea las cuentas y perfiles de cliente demo.
     */
    public function run(): void
    {
        foreach (self::CLIENTES as $indice => $datos) {
            $numero = $indice + 1;
            $email = sprintf('cliente%d@demo.mercadoinmueble.com', $numero);

            $user = User::withTrashed()->firstOrNew(['email' => $email]);

            // `forceFill`: email_verified_at y deleted_at no son fillable y la
            // app evita el descarte silencioso de atributos.
            $user->forceFill([
                'name' => $datos['name'],
                'password' => Hash::make(UserFactory::passwordDemo()),
                'role' => Role::Cliente,
                'phone' => sprintf('+591 7%06d', 400000 + $numero),
                'is_active' => true,
                'email_verified_at' => now(),
                'deleted_at' => null,
            ])->save();

            $cliente = Cliente::withTrashed()->firstOrNew(['user_id' => $user->getKey()]);

            $cliente->forceFill([
                'telefono' => sprintf('+591 7%06d', 400000 + $numero),
                'presupuesto_min' => $datos['min'],
                'presupuesto_max' => $datos['max'],
                'moneda' => Moneda::Usd,
                'tipo_propiedad_interes' => $datos['tipo'],
                'ciudad_interes' => $datos['ciudad'],
                'habitaciones_min' => $datos['habitaciones'],
                'preferencias' => [
                    'acepta_mascotas' => $indice % 3 === 0,
                    'cerca_de_transporte' => $indice % 2 === 0,
                    'con_estacionamiento' => $datos['habitaciones'] >= 2,
                ],
                'acepta_terminos' => true,
                'recibe_novedades' => $numero % 2 === 0,
                'deleted_at' => null,
            ])->save();

            $user->vincularPerfil($cliente);
        }

        $this->command?->info(sprintf('✔ Clientes: %d cuentas (contraseña %s).', count(self::CLIENTES), UserFactory::passwordDemo()));
    }
}
