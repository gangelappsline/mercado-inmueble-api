<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
|--------------------------------------------------------------------------
| Bootstrap de la aplicación (API-only)
|--------------------------------------------------------------------------
| No se registra el grupo `web` para las rutas de la API: la autenticación es
| stateless mediante Passport. `routes/web.php` sólo expone un índice JSON.
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Toda la aplicación responde JSON, incluso ante errores.
        $middleware->prepend(ForceJsonResponse::class);

        // Cabeceras de seguridad + X-Request-Id en todas las respuestas.
        $middleware->append(SecurityHeaders::class);

        // Alias usados en routes/api.php
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'activo' => EnsureAccountIsActive::class,
            'json' => ForceJsonResponse::class,
        ]);

        // El grupo `api` aplica `throttle:api` (límite por rol, ver AppServiceProvider).
        // La API es stateless: el token viaja en `Authorization: Bearer ...`.
        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ApiExceptionHandler::register($exceptions);
    })
    ->create();
