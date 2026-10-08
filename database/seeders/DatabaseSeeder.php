<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder principal: `php artisan db:seed` (o `migrate:fresh --seed`).
 *
 * Crea catálogos, cuentas demo de los tres roles, 26 propiedades con medios y
 * actividad comercial (intereses, mensajes, citas, favoritos y reportes).
 * Nunca se ejecuta en producción.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Ejecuta los seeders en orden de dependencias.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('⚠ Los datos de demostración no se siembran en producción.');

            return;
        }

        $this->call([
            AmenidadSeeder::class,
            InmobiliariaSeeder::class,
            VendedorSeeder::class,
            ClienteSeeder::class,
            PropiedadSeeder::class,
            InteraccionSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('✅ Datos de demostración listos. Contraseña de todas las cuentas demo: Password123!');
    }
}
