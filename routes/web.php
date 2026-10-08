<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
| La aplicación es API-only: no sirve vistas ni assets. La raíz devuelve un
| índice JSON con los enlaces útiles del despliegue.
*/

Route::get('/', static function (): array {
    return [
        'aplicacion' => config('app.name'),
        'version' => 'v1',
        'api' => url('/api/v1'),
        'documentacion' => url('/docs/api'),
        'contrato_openapi' => url('/openapi.json'),
        'salud' => url('/up'),
    ];
})->name('inicio');
