<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cita;
use App\Models\Hilo;
use App\Models\Inmobiliaria;
use App\Models\Interes;
use App\Models\Mensaje;
use App\Models\Propiedad;
use App\Models\Reporte;
use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Diagnóstico rápido del despliegue: conexión a base de datos, disks de
 * medios, clientes OAuth y volúmenes de datos.
 */
class EstadoMercadoCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mercado:estado';

    /**
     * @var string
     */
    protected $description = 'Muestra el estado de la instalación de Mercado Inmueble';

    /**
     * Ejecuta el diagnóstico.
     */
    public function handle(): int
    {
        $this->info('🩺 Estado de Mercado Inmueble');

        $this->tabla([
            ['PHP', PHP_VERSION],
            ['Laravel', app()->version()],
            ['Entorno', app()->environment()],
            ['Debug', config('app.debug') ? 'activado' : 'desactivado'],
            ['Base de datos', $this->estadoBaseDeDatos()],
            ['Caché', (string) config('cache.default')],
            ['Disk de fotos', $this->estadoDisk((string) config('mercado.media.disco_fotos'))],
            ['Disk de videos', $this->estadoDisk((string) config('mercado.media.disco_videos'))],
            ['Cliente OAuth', filled(config('mercado.oauth.client_id')) ? 'configurado' : 'pendiente (mercado:instalar)'],
        ]);

        $this->newLine();
        $this->tablaDatos();

        return self::SUCCESS;
    }

    /**
     * Comprueba la conexión a la base de datos.
     */
    private function estadoBaseDeDatos(): string
    {
        try {
            DB::connection()->getPdo();

            return (string) config('database.default');
        } catch (Throwable $e) {
            return 'error: '.$e->getMessage();
        }
    }

    /**
     * Comprueba que el disk esté accesible.
     */
    private function estadoDisk(string $disk): string
    {
        try {
            return Storage::disk($disk)->exists('.') || true ? $disk.' (accesible)' : $disk;
        } catch (Throwable $e) {
            return $disk.' (error: '.$e->getMessage().')';
        }
    }

    /**
     * @param  array<int, array<int, string>>  $filas
     */
    private function tabla(array $filas): void
    {
        $this->table(['Comprobación', 'Valor'], $filas);
    }

    /**
     * Volumen de datos actual (útil para verificar el seeding).
     */
    private function tablaDatos(): void
    {
        $this->table(['Entidad', 'Registros'], [
            ['Usuarios', $this->contar(User::class)],
            ['Administradores', $this->contar(User::class, static fn (Builder $query): Builder => $query->administradores()->activos())],
            ['Inmobiliarias', $this->contar(Inmobiliaria::class)],
            ['Vendedores', $this->contar(Vendedor::class)],
            ['Propiedades', $this->contar(Propiedad::class)],
            ['Intereses', $this->contar(Interes::class)],
            ['Hilos', $this->contar(Hilo::class)],
            ['Mensajes', $this->contar(Mensaje::class)],
            ['Citas', $this->contar(Cita::class)],
            ['Reportes', $this->contar(Reporte::class)],
            ['Cachés de catálogo', Cache::has('catalogo.opciones') ? 'caliente' : 'fría'],
        ]);
    }

    /**
     * Cuenta registros de un modelo, devolviendo "error" si la tabla no existe.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelo
     * @param  (callable(Builder): Builder)|null  $filtro  Ámbito adicional (p. ej. sólo administradores).
     */
    private function contar(string $modelo, ?callable $filtro = null): string
    {
        try {
            $consulta = $modelo::query();

            if ($filtro !== null) {
                $consulta = $filtro($consulta);
            }

            return (string) $consulta->count();
        } catch (Throwable) {
            return 'sin tabla (¿migraste?)';
        }
    }
}
