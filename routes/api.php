<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Rutas de la API (v1)
|--------------------------------------------------------------------------
|
| Todas las rutas llevan el prefijo /api (definido en bootstrap/app.php) y
| aquí se versionan con /v1. La autenticación es stateless con Passport
| (`Authorization: Bearer <access_token>`).
|
| Convenciones:
| - `auth:api`  exige token válido; `activo` bloquea cuentas desactivadas;
|   `role:...` restringe el panel por rol.
| - `throttle:auth` y `throttle:contacto` limitan los endpoints sensibles.
| - Las descargas de archivos privados van firmadas (middleware `signed`).
|
*/

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Cliente as ClienteApi;
use App\Http\Controllers\Api\Inmobiliaria as InmobiliariaApi;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\Public as PublicApi;
use App\Http\Controllers\Api\Vendedor as VendedorApi;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    /*
    |----------------------------------------------------------------------
    | Autenticación y sesión
    |----------------------------------------------------------------------
    */
    Route::prefix('auth')->middleware('throttle:auth')->group(function (): void {
        Route::post('register/{rol}', [AuthController::class, 'registrar'])
            ->whereIn('rol', ['inmobiliaria', 'vendedor', 'cliente'])
            ->name('auth.register');

        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset');

        Route::middleware(['auth:api', 'activo'])->group(function (): void {
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::post('logout-all', [AuthController::class, 'logoutTodas'])->name('auth.logout-all');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Catálogo público
    |----------------------------------------------------------------------
    */
    Route::get('propiedades', [PublicApi\PropiedadController::class, 'index'])->name('propiedades.index');
    Route::get('propiedades/destacadas', [PublicApi\PropiedadController::class, 'destacadas'])->name('propiedades.destacadas');
    Route::get('propiedades/{propiedad}', [PublicApi\PropiedadController::class, 'show'])->name('propiedades.show');

    Route::get('amenidades', [PublicApi\CatalogoController::class, 'amenidades'])->name('catalogo.amenidades');
    Route::get('ciudades', [PublicApi\CatalogoController::class, 'ciudades'])->name('catalogo.ciudades');
    Route::get('catalogo/opciones', [PublicApi\CatalogoController::class, 'opciones'])->name('catalogo.opciones');

    Route::get('inmobiliarias', [PublicApi\InmobiliariaController::class, 'index'])->name('inmobiliarias.index');
    Route::get('inmobiliarias/{inmobiliaria}', [PublicApi\InmobiliariaController::class, 'show'])->name('inmobiliarias.show');

    Route::post('contacto', [PublicApi\ContactoController::class, 'store'])
        ->middleware('throttle:contacto')
        ->name('contacto.store');

    /*
    |----------------------------------------------------------------------
    | Descargas firmadas (disks privados: videos y adjuntos)
    |----------------------------------------------------------------------
    */
    Route::get('media/videos/{video}', [MediaController::class, 'video'])
        ->middleware('signed')
        ->name('media.video');

    Route::get('media/adjuntos/{mensaje}', [MediaController::class, 'adjunto'])
        ->middleware('signed')
        ->name('media.adjunto');

    /*
    |----------------------------------------------------------------------
    | Panel del anunciante (inmobiliaria y vendedor)
    |----------------------------------------------------------------------
    | La misma estructura de endpoints se monta dos veces: una por rol, con
    | controladores propios que heredan la lógica común del panel.
    */
    $panel = static function (string $espacio, string $rol): void {
        $propiedades = "{$espacio}\\PropiedadController";
        $citas = "{$espacio}\\CitaController";
        $interesados = "{$espacio}\\InteresController";
        $mensajes = "{$espacio}\\MensajeController";
        $reportes = "{$espacio}\\ReporteController";
        $perfil = "{$espacio}\\PerfilController";

        Route::middleware(["role:{$rol}"])->group(function () use ($propiedades, $citas, $interesados, $mensajes, $reportes, $perfil): void {
            // Publicaciones
            Route::apiResource('propiedades', $propiedades)
                ->parameters(['propiedades' => 'propiedad'])
                ->only(['index', 'store', 'show', 'update', 'destroy']);

            Route::post('propiedades/{propiedad}/publicar', [$propiedades, 'publicar'])->name('propiedades.publicar');
            Route::post('propiedades/{propiedad}/pausar', [$propiedades, 'pausar'])->name('propiedades.pausar');
            Route::post('propiedades/{propiedad}/cerrar-operacion', [$propiedades, 'cerrarOperacion'])->name('propiedades.cerrar');
            Route::get('propiedades/{propiedad}/estadisticas', [$propiedades, 'estadisticas'])->name('propiedades.estadisticas');

            // Medios
            Route::post('propiedades/{propiedad}/fotos', [$propiedades, 'subirFotos'])->name('propiedades.fotos.store');
            Route::patch('propiedades/{propiedad}/fotos/orden', [$propiedades, 'reordenarFotos'])->name('propiedades.fotos.orden');
            Route::patch('propiedades/{propiedad}/fotos/{foto}/principal', [$propiedades, 'fotoPrincipal'])->name('propiedades.fotos.principal');
            Route::delete('propiedades/{propiedad}/fotos/{foto}', [$propiedades, 'eliminarFoto'])->name('propiedades.fotos.destroy');
            Route::post('propiedades/{propiedad}/video', [$propiedades, 'subirVideo'])->name('propiedades.video.store');
            Route::delete('propiedades/{propiedad}/video', [$propiedades, 'eliminarVideo'])->name('propiedades.video.destroy');

            // Agenda de citas
            Route::get('citas', [$citas, 'index'])->name('citas.index');
            Route::get('citas/agenda', [$citas, 'agenda'])->name('citas.agenda');
            Route::get('citas/{cita}', [$citas, 'show'])->name('citas.show');
            Route::patch('citas/{cita}/confirmar', [$citas, 'confirmar'])->name('citas.confirmar');
            Route::patch('citas/{cita}/reprogramar', [$citas, 'reprogramar'])->name('citas.reprogramar');
            Route::patch('citas/{cita}/cancelar', [$citas, 'cancelar'])->name('citas.cancelar');
            Route::patch('citas/{cita}/completar', [$citas, 'completar'])->name('citas.completar');
            Route::patch('citas/{cita}/no-asistio', [$citas, 'marcarNoAsistio'])->name('citas.no-asistio');

            // Interesados
            Route::get('interesados', [$interesados, 'index'])->name('interesados.index');
            Route::get('interesados/{interes}', [$interesados, 'show'])->name('interesados.show');
            Route::post('interesados/{interes}/responder', [$interesados, 'responder'])->name('interesados.responder');
            Route::patch('interesados/{interes}/estado', [$interesados, 'cambiarEstado'])->name('interesados.estado');

            // Mensajes
            Route::get('mensajes', [$mensajes, 'index'])->name('mensajes.index');
            Route::get('mensajes/{hilo}', [$mensajes, 'show'])->name('mensajes.show');
            Route::post('mensajes/{hilo}', [$mensajes, 'store'])->name('mensajes.store');
            Route::patch('mensajes/{hilo}/cerrar', [$mensajes, 'cerrar'])->name('mensajes.cerrar');
            Route::patch('mensajes/{hilo}/reabrir', [$mensajes, 'reabrir'])->name('mensajes.reabrir');

            // Reportes
            Route::get('reportes/resumen', [$reportes, 'resumen'])->name('reportes.resumen');
            Route::get('reportes/propiedades-mas-vistas', [$reportes, 'propiedadesMasVistas'])->name('reportes.mas-vistas');
            Route::get('reportes/conversion', [$reportes, 'conversion'])->name('reportes.conversion');
            Route::get('reportes/ingresos', [$reportes, 'ingresos'])->name('reportes.ingresos');
            Route::get('reportes/exportar', [$reportes, 'exportar'])->name('reportes.exportar');

            // Perfil y logotipo
            Route::get('perfil', [$perfil, 'show'])->name('perfil.show');
            Route::match(['put', 'patch'], 'perfil', [$perfil, 'actualizar'])->name('perfil.update');
            Route::post('perfil/logo', [$perfil, 'subirLogo'])->name('perfil.logo.store');
            Route::delete('perfil/logo', [$perfil, 'eliminarLogo'])->name('perfil.logo.destroy');
        });
    };

    Route::prefix('inmobiliaria')
        ->middleware(['auth:api', 'activo'])
        ->group(static fn () => $panel(InmobiliariaApi::class, 'inmobiliaria'));

    Route::prefix('vendedor')
        ->middleware(['auth:api', 'activo'])
        ->group(static fn () => $panel(VendedorApi::class, 'vendedor'));

    /*
    |----------------------------------------------------------------------
    | Panel del cliente
    |----------------------------------------------------------------------
    */
    Route::prefix('cliente')
        ->middleware(['auth:api', 'activo', 'role:cliente'])
        ->group(function (): void {
            // Búsqueda personalizada
            Route::get('propiedades', [ClienteApi\PropiedadController::class, 'index'])->name('cliente.propiedades.index');
            Route::get('propiedades/{propiedad}', [ClienteApi\PropiedadController::class, 'show'])->name('cliente.propiedades.show');

            // Favoritos
            Route::get('favoritos', [ClienteApi\FavoritoController::class, 'index'])->name('cliente.favoritos.index');
            Route::post('favoritos', [ClienteApi\FavoritoController::class, 'store'])->name('cliente.favoritos.store');
            Route::patch('favoritos/{propiedad}', [ClienteApi\FavoritoController::class, 'update'])->name('cliente.favoritos.update');
            Route::delete('favoritos/{propiedad}', [ClienteApi\FavoritoController::class, 'destroy'])->name('cliente.favoritos.destroy');

            // Intereses
            Route::get('intereses', [ClienteApi\InteresController::class, 'index'])->name('cliente.intereses.index');
            Route::post('propiedades/{propiedad}/interes', [ClienteApi\InteresController::class, 'store'])->name('cliente.intereses.store');
            Route::get('intereses/{interes}', [ClienteApi\InteresController::class, 'show'])->name('cliente.intereses.show');
            Route::delete('intereses/{interes}', [ClienteApi\InteresController::class, 'destroy'])->name('cliente.intereses.destroy');

            // Mensajes
            Route::get('mensajes', [ClienteApi\MensajeController::class, 'index'])->name('cliente.mensajes.index');
            Route::get('mensajes/{hilo}', [ClienteApi\MensajeController::class, 'show'])->name('cliente.mensajes.show');
            Route::post('mensajes/{hilo}', [ClienteApi\MensajeController::class, 'store'])->name('cliente.mensajes.store');

            // Citas
            Route::get('citas', [ClienteApi\CitaController::class, 'index'])->name('cliente.citas.index');
            Route::post('propiedades/{propiedad}/citas', [ClienteApi\CitaController::class, 'store'])->name('cliente.citas.store');
            Route::get('citas/{cita}', [ClienteApi\CitaController::class, 'show'])->name('cliente.citas.show');
            Route::patch('citas/{cita}/reprogramar', [ClienteApi\CitaController::class, 'reprogramar'])->name('cliente.citas.reprogramar');
            Route::patch('citas/{cita}/cancelar', [ClienteApi\CitaController::class, 'cancelar'])->name('cliente.citas.cancelar');

            // Perfil
            Route::get('perfil', [ClienteApi\PerfilController::class, 'show'])->name('cliente.perfil.show');
            Route::match(['put', 'patch'], 'perfil', [ClienteApi\PerfilController::class, 'actualizar'])->name('cliente.perfil.update');
        });
});
