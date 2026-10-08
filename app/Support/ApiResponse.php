<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Illuminate\Contracts\Support\Responsable;

/**
 * Construye el sobre (envelope) uniforme de todas las respuestas de la API.
 *
 * Éxito:  {"success": true, "message": "...", "data": ..., "meta": {...}}
 * Error:  {"success": false, "message": "...", "code": "...", "errors": {...}}
 *
 * Cuando el payload es un API Resource, se integran `data`, `links` y `meta`
 * generados por Laravel (paginación estándar de los Resource Collections).
 */
final class ApiResponse
{
    /**
     * Respuesta exitosa con payload opcional.
     *
     * @param  mixed  $data  API Resource, colección, modelo, array o escalar.
     * @param  array<string, mixed>  $meta  Metadata adicional a fusionar.
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message ?? __('messages.ok'),
        ];

        if ($data instanceof Responsable) {
            $payload = array_merge($payload, (array) $data->toResponse(request())->getData(true));
        } elseif ($data instanceof JsonResource || $data instanceof ResourceCollection || $data instanceof ResourceResponse) {
            $payload = array_merge($payload, (array) $data->toResponse(request())->getData(true));
        } elseif ($data !== null) {
            $payload['data'] = $data;
        }

        if ($meta !== []) {
            $payload['meta'] = array_merge((array) ($payload['meta'] ?? []), $meta);
        }

        return response()->json($payload, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Respuesta de recurso creado (HTTP 201).
     *
     * @param  array<string, mixed>  $meta
     */
    public static function created(mixed $data = null, ?string $message = null, array $meta = []): JsonResponse
    {
        return self::success($data, $message ?? __('messages.creado'), 201, $meta);
    }

    /**
     * Respuesta sin contenido útil (HTTP 204).
     */
    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Respuesta de error normalizada.
     *
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, mixed>  $meta
     */
    public static function error(
        string $message,
        int $status = 400,
        string $code = 'ERROR',
        array $errors = [],
        array $meta = [],
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
