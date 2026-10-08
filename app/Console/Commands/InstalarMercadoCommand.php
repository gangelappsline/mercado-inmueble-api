<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AuthService;
use App\Services\CatalogoService;
use Database\Factories\UserFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Instalador de la plataforma: prepara la aplicación para el primer arranque.
 *
 * Ejecuta en orden: APP_KEY, claves de Passport, migraciones, enlace de
 * storage, clientes OAuth first-party (escribiendo las credenciales en el
 * `.env`) y, opcionalmente, los datos de demostración.
 */
class InstalarMercadoCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mercado:instalar
        {--fresh : Recrea la base de datos antes de migrar (migrate:fresh)}
        {--seed : Siembra los datos de demostración}
        {--force : Vuelve a crear los clientes OAuth aunque ya existan}';

    /**
     * @var string
     */
    protected $description = 'Instala Mercado Inmueble: claves, migraciones, Passport y datos iniciales';

    /**
     * Ejecuta la instalación completa.
     */
    public function handle(AuthService $auth, CatalogoService $catalogo): int
    {
        $this->info('🏗️  Instalando Mercado Inmueble...');

        $this->prepararAplicacion();
        $this->prepararBaseDeDatos();
        $this->prepararStorage();
        $this->prepararPassport($auth);

        $catalogo->olvidar();

        if ($this->option('seed')) {
            $this->call('db:seed', ['--force' => true]);
        }

        $this->call('optimize:clear');

        $this->mostrarResumen();

        return self::SUCCESS;
    }

    /**
     * Genera APP_KEY y publica los archivos base si faltan.
     */
    private function prepararAplicacion(): void
    {
        if (blank(config('app.key'))) {
            $this->components->task('Generando APP_KEY', fn (): int => $this->call('key:generate', ['--force' => true]));
        }
    }

    /**
     * Crea/actualiza el esquema de base de datos.
     */
    private function prepararBaseDeDatos(): void
    {
        $comando = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

        $this->components->task('Migrando la base de datos', function () use ($comando): void {
            $this->call($comando, ['--force' => true]);
        });
    }

    /**
     * Crea el enlace simbólico de storage/public para las fotos.
     */
    private function prepararStorage(): void
    {
        $this->components->task('Enlazando storage público', function (): void {
            try {
                if (! File::exists(public_path('storage'))) {
                    $this->call('storage:link');
                }
            } catch (Throwable $e) {
                $this->warn('No se pudo crear el enlace de storage: '.$e->getMessage());
            }
        });
    }

    /**
     * Genera las claves de cifrado y los clientes OAuth first-party.
     */
    private function prepararPassport(AuthService $auth): void
    {
        $this->components->task('Generando claves de Passport', function (): void {
            if (! File::exists(storage_path('oauth-private.key'))) {
                $this->call('passport:keys', ['--force' => true]);
            }
        });

        $configurado = filled(config('mercado.oauth.client_id')) && ! $this->option('force');

        if ($configurado) {
            $this->components->info('Clientes OAuth ya configurados (usa --force para regenerarlos).');

            return;
        }

        $this->components->task('Creando clientes OAuth (password + personal)', function () use ($auth): void {
            $credenciales = $auth->asegurarClientesOAuth((string) config('app.name'));

            $this->escribirEnv([
                'PASSPORT_CLIENT_ID' => (string) $credenciales['client_id'],
                'PASSPORT_CLIENT_SECRET' => (string) $credenciales['client_secret'],
                'PASSPORT_PERSONAL_CLIENT_ID' => (string) $credenciales['personal_client_id'],
            ]);
        });
    }

    /**
     * Escribe (o reemplaza) variables en el archivo `.env`.
     *
     * @param  array<string, string>  $valores
     */
    private function escribirEnv(array $valores): void
    {
        $ruta = base_path('.env');

        if (! File::exists($ruta)) {
            return;
        }

        $contenido = (string) File::get($ruta);

        foreach ($valores as $clave => $valor) {
            $linea = sprintf('%s=%s', $clave, $valor);

            if (preg_match('/^'.preg_quote($clave, '/').'=.*$/m', $contenido) === 1) {
                $contenido = (string) preg_replace('/^'.preg_quote($clave, '/').'=.*$/m', $linea, $contenido);

                continue;
            }

            $contenido = rtrim($contenido, "\n")."\n".$linea."\n";
        }

        File::put($ruta, $contenido);
    }

    /**
     * Muestra las credenciales demo y los siguientes pasos.
     */
    private function mostrarResumen(): void
    {
        $this->newLine();
        $this->info('✅ Instalación completada.');

        if ($this->option('seed')) {
            $this->line('   Cuentas demo (contraseña: '.UserFactory::PASSWORD_DEMO.'):');
            $this->line('   • inmobiliaria1@demo.mercadoinmueble.com … inmobiliaria5@demo.mercadoinmueble.com');
            $this->line('   • vendedor1@demo.mercadoinmueble.com … vendedor3@demo.mercadoinmueble.com');
            $this->line('   • cliente1@demo.mercadoinmueble.com … cliente10@demo.mercadoinmueble.com');
        }

        $this->newLine();
        $this->line('   Siguientes pasos:');
        $this->line('   1. php artisan serve  (o configura tu servidor web)');
        $this->line('   2. Documentación OpenAPI: /docs/api');
        $this->line('   3. Generar el contrato: php artisan openapi');
    }
}
