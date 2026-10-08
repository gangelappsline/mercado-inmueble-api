<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Enums\EstadoInteres;
use App\Http\Controllers\Api\Panel\Concerns\ResuelvePropietario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Interes\CambiarEstadoInteresRequest;
use App\Http\Requests\Interes\IndexInteresRequest;
use App\Http\Requests\Interes\ResponderMensajeRequest;
use App\Http\Resources\InteresResource;
use App\Http\Resources\MensajeResource;
use App\Models\Interes;
use App\Services\InteresService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bandeja de interesados (leads) del anunciante: listado, detalle, respuesta
 * con adjuntos y cambios de estado del embudo comercial.
 */
class InteresController extends Controller
{
    use ResuelvePropietario;

    public function __construct(
        private readonly InteresService $intereses,
    ) {
    }

    /**
     * Lista los interesados con filtros por estado y propiedad.
     */
    public function index(IndexInteresRequest $request): JsonResponse
    {
        $paginado = $this->intereses->listarParaPropietario(
            $this->propietario($request),
            $request->filtros(),
            $request->porPagina(),
        );

        return ApiResponse::success(InteresResource::collection($paginado), __('messages.ok'));
    }

    /**
     * Detalle del interés con su propiedad, cliente e hilo de mensajes.
     */
    public function show(Request $request, Interes $interes): JsonResponse
    {
        $this->authorize('view', $interes);

        $interes->load(['propiedad.fotoPrincipal', 'cliente.user', 'hilo.ultimoMensaje']);

        return ApiResponse::success((new InteresResource($interes))->resolve($request), __('messages.ok'));
    }

    /**
     * Responde al interesado: crea el mensaje en el hilo (con adjunto opcional).
     */
    public function responder(ResponderMensajeRequest $request, Interes $interes): JsonResponse
    {
        $this->authorize('responder', $interes);

        $mensaje = $this->intereses->responder(
            $interes,
            $request->user(),
            (string) $request->validated('cuerpo'),
            $request->file('adjunto'),
        );

        if ($request->boolean('cerrar_interes')) {
            $this->intereses->cambiarEstado($interes, EstadoInteres::Atendido);
        }

        return ApiResponse::created((new MensajeResource($mensaje))->resolve($request), __('messages.mensaje_enviado'));
    }

    /**
     * Cambia el estado del interés (atendido, cerrado, descartado).
     */
    public function cambiarEstado(CambiarEstadoInteresRequest $request, Interes $interes): JsonResponse
    {
        $this->authorize('cambiarEstado', $interes);

        $interes = $this->intereses->cambiarEstado($interes, EstadoInteres::from((string) $request->validated('estado')));

        return ApiResponse::success((new InteresResource($interes))->resolve($request), __('messages.actualizado'));
    }
}
