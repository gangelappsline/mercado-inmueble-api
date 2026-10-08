<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Inmobiliaria;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cinco inmobiliarias de demostración con sus cuentas de acceso
 * (contraseña común `Password123!`, ver README).
 */
class InmobiliariaSeeder extends Seeder
{
    /**
     * Datos fijos de las inmobiliarias demo.
     *
     * @var array<int, array<string, string>>
     */
    private const INMOBILIARIAS = [
        [
            'email' => 'inmobiliaria1@demo.mercadoinmueble.com',
            'name' => 'Mariana Quiroga',
            'razon_social' => 'Urbana Propiedades S.R.L.',
            'nombre_comercial' => 'Urbana Propiedades',
            'ciudad' => 'La Paz',
            'estado_provincia' => 'La Paz',
            'descripcion' => 'Especialistas en departamentos y oficinas en el eje central de La Paz, con más de 12 años acompañando a familias y empresas.',
            'web' => 'https://urbanapropiedades.demo.bo',
        ],
        [
            'email' => 'inmobiliaria2@demo.mercadoinmueble.com',
            'name' => 'Rodrigo Salazar',
            'razon_social' => 'Andes Bienes Raíces S.R.L.',
            'nombre_comercial' => 'Andes Bienes Raíces',
            'ciudad' => 'Santa Cruz de la Sierra',
            'estado_provincia' => 'Santa Cruz',
            'descripcion' => 'Casas y terrenos en los barrios de mayor crecimiento de Santa Cruz. Asesoría legal y crediticia incluida.',
            'web' => 'https://andesbienes.demo.bo',
        ],
        [
            'email' => 'inmobiliaria3@demo.mercadoinmueble.com',
            'name' => 'Carla Villarroel',
            'razon_social' => 'Cochabamba Casa & Terreno S.R.L.',
            'nombre_comercial' => 'Casa & Terreno',
            'ciudad' => 'Cochabamba',
            'estado_provincia' => 'Cochabamba',
            'descripcion' => 'Venta y alquiler de viviendas familiares en el valle cochabambino, con recorridos virtuales 360°.',
            'web' => 'https://casayterreno.demo.bo',
        ],
        [
            'email' => 'inmobiliaria4@demo.mercadoinmueble.com',
            'name' => 'Julio Choque',
            'razon_social' => 'Altiplano Inmobiliaria S.R.L.',
            'nombre_comercial' => 'Altiplano Inmobiliaria',
            'ciudad' => 'El Alto',
            'estado_provincia' => 'La Paz',
            'descripcion' => 'Oportunidades de inversión y vivienda en El Alto. Financiamiento directo disponible.',
            'web' => 'https://altiplanoinmobiliaria.demo.bo',
        ],
        [
            'email' => 'inmobiliaria5@demo.mercadoinmueble.com',
            'name' => 'Daniela Ríos',
            'razon_social' => 'Sur Propiedades S.R.L.',
            'nombre_comercial' => 'Sur Propiedades',
            'ciudad' => 'Sucre',
            'estado_provincia' => 'Chuquisaca',
            'descripcion' => 'Inmuebles con valor histórico y casas coloniales restauradas en Sucre, patrimonio de la humanidad.',
            'web' => 'https://surpropiedades.demo.bo',
        ],
    ];

    /**
     * Crea las cuentas e inmobiliarias demo.
     */
    public function run(): void
    {
        foreach (self::INMOBILIARIAS as $indice => $datos) {
            $user = User::withTrashed()->updateOrCreate(
                ['email' => $datos['email']],
                [
                    'name' => $datos['name'],
                    'password' => Hash::make(UserFactory::passwordDemo()),
                    'role' => Role::Inmobiliaria,
                    'phone' => sprintf('+591 7%06d', 100000 + $indice),
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'deleted_at' => null,
                ],
            );

            $inmobiliaria = Inmobiliaria::withTrashed()->updateOrCreate(
                ['ruc' => sprintf('10%08d', 25000000 + $indice)],
                [
                    'user_id' => $user->getKey(),
                    'razon_social' => $datos['razon_social'],
                    'nombre_comercial' => $datos['nombre_comercial'],
                    'direccion' => 'Av. Central #'.(100 + $indice).', '.$datos['ciudad'],
                    'telefono' => sprintf('+591 3%07d', 3000000 + $indice),
                    'telefono_alternativo' => sprintf('+591 7%06d', 200000 + $indice),
                    'web' => $datos['web'],
                    'descripcion' => $datos['descripcion'],
                    'ciudad' => $datos['ciudad'],
                    'estado_provincia' => $datos['estado_provincia'],
                    'pais' => 'Bolivia',
                    'verificado' => $indice < 3,
                    'deleted_at' => null,
                ],
            );

            $user->vincularPerfil($inmobiliaria);
        }

        $this->command?->info(sprintf('✔ Inmobiliarias: %d cuentas (contraseña %s).', count(self::INMOBILIARIAS), UserFactory::passwordDemo()));
    }
}
