<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Excepción de regla de negocio: se traduce en una respuesta HTTP controlada
 * (422 por defecto) en lugar de un error 500. No se reporta en los logs.
 */
class BusinessException extends Exception
{
    /**
     * @param  string  $message  Mensaje en español para el cliente.
     * @param  array<string, array<int, string>>  $errors  Errores por campo.
     * @param  int  $status  Código HTTP (422 validación de negocio, 409 conflicto...).
     * @param  string  $errorCode  Código interno legible por máquina.
     * @param  array<string, mixed>  $context  Datos extra para el cliente (no sensibles).
     */
    public function __construct(
        string $message,
        protected array $errors = [],
        protected int $status = 422,
        protected string $errorCode = 'REGLA_DE_NEGOCIO',
        protected array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Errores por campo asociados a la violación de la regla.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Código HTTP sugerido para la respuesta.
     */
    public function statusCode(): int
    {
        return $this->status;
    }

    /**
     * Código interno del error (para clientes y documentación).
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Contexto adicional que se devuelve en `meta`.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Respuesta HTTP normalizada (invocada automáticamente por el handler).
     */
    public function render(?Request $request = null): JsonResponse
    {
        return ApiResponse::error(
            $this->getMessage(),
            $this->status,
            $this->errorCode,
            $this->errors,
            $this->context,
        );
    }

    /**
     * Atajo para conflictos (409), por ejemplo un video duplicado.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    public static function conflicto(string $mensaje, array $errors = [], string $codigo = 'CONFLICTO'): self
    {
        return new self($mensaje, $errors, 409, $codigo);
    }

    /**
     * Atajo para recursos inexistentes (404).
     */
    public static function noEncontrado(string $mensaje, string $codigo = 'NO_ENCONTRADO'): self
    {
        return new self($mensaje, [], 404, $codigo);
    }
}
