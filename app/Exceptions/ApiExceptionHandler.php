<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Centraliza el formato de todas las respuestas de error de la API
 * (mismo sobre que las respuestas exitosas, ver ApiResponse).
 */
final class ApiExceptionHandler
{
    /**
     * Registra los renderers y políticas de reporte de la API.
     */
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request, Throwable $e): bool => $request->is('api/*', 'oauth/*') || $request->expectsJson()
        );

        $exceptions->dontReport([
            BusinessException::class,
            ValidationException::class,
            AuthenticationException::class,
            AuthorizationException::class,
        ]);

        $exceptions->render(
            static fn (BusinessException $e, Request $request) => $e->errors() === []
                ? \App\Support\ApiResponse::error($e->getMessage(), $e->statusCode(), $e->errorCode(), [], $e->context())
                : \App\Support\ApiResponse::error($e->getMessage(), $e->statusCode(), $e->errorCode(), $e->errors(), $e->context())
        );

        $exceptions->render(
            static fn (ValidationException $e, Request $request) => \App\Support\ApiResponse::error(
                __('messages.datos_invalidos'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'VALIDATION_ERROR',
                $e->errors(),
            )
        );

        $exceptions->render(
            static fn (AuthenticationException $e, Request $request) => \App\Support\ApiResponse::error(
                __('messages.no_autenticado'),
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHENTICATED',
            )
        );

        $exceptions->render(
            static fn (AuthorizationException|AccessDeniedHttpException $e, Request $request) => \App\Support\ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : __('messages.no_autorizado'),
                Response::HTTP_FORBIDDEN,
                'FORBIDDEN',
            )
        );

        $exceptions->render(
            static fn (ModelNotFoundException $e, Request $request) => \App\Support\ApiResponse::error(
                __('messages.recurso_no_encontrado'),
                Response::HTTP_NOT_FOUND,
                'NOT_FOUND',
            )
        );

        $exceptions->render(
            static fn (NotFoundHttpException $e, Request $request) => \App\Support\ApiResponse::error(
                __('messages.ruta_no_encontrada'),
                Response::HTTP_NOT_FOUND,
                'ROUTE_NOT_FOUND',
            )
        );

        $exceptions->render(
            static fn (MethodNotAllowedHttpException $e, Request $request) => \App\Support\ApiResponse::error(
                __('messages.metodo_no_permitido'),
                Response::HTTP_METHOD_NOT_ALLOWED,
                'METHOD_NOT_ALLOWED',
            )
        );

        $exceptions->render(
            static fn (TooManyRequestsHttpException $e, Request $request) => \App\Support\ApiResponse::error(
                __('messages.demasiadas_peticiones'),
                Response::HTTP_TOO_MANY_REQUESTS,
                'TOO_MANY_REQUESTS',
                [],
                ['retry_after' => $e->getHeaders()['Retry-After'] ?? null],
            )
        );

        $exceptions->render(
            static fn (TokenMismatchException $e, Request $request) => \App\Support\ApiResponse::error(
                __('messages.sesion_expirada'),
                Response::HTTP_UNAUTHORIZED,
                'SESSION_EXPIRED',
            )
        );

        $exceptions->render(
            static fn (QueryException $e, Request $request) => \App\Support\ApiResponse::error(
                config('app.debug') ? $e->getMessage() : __('messages.error_interno'),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'DATABASE_ERROR',
            )
        );

        $exceptions->render(
            static fn (HttpException $e, Request $request) => \App\Support\ApiResponse::error(
                $e->getMessage() !== '' ? $e->getMessage() : __('messages.error_http'),
                $e->getStatusCode(),
                'HTTP_ERROR',
            )
        );

        $exceptions->render(
            static fn (Throwable $e, Request $request) => \App\Support\ApiResponse::error(
                config('app.debug') ? $e->getMessage() : __('messages.error_interno'),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'SERVER_ERROR',
                [],
                config('app.debug') ? ['exception' => $e::class] : [],
            )
        );
    }
}
