<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Interes\IndexHiloRequest;
use App\Http\Requests\Interes\StoreMensajeRequest;
use App\Http\Resources\HiloResource;
use App\Http\Resources\MensajeResource;
use App\Models\Hilo;
use App\Services\MensajeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bandeja de mensajes del panel: hilos con los interesados, envío de
 * respuestas y cierre/reapertura de la conversación.
 */
class MensajeController extends Controller
{
    public function __construct(
        private readonly MensajeService $mensajes,
    ) {
    }

    /**
     * Lista los hilos del usuario (inmobiliaria o vendedor) con filtros.
     */
    public function index(IndexHiloRequest $request): JsonResponse
    {
        $paginado = $this->mensajes->hilosPara(
            $request->user(),
            $request->filtros(),
            $request->porPagina(),
        );

        return ApiResponse::success(HiloResource::collection($paginado), __('messages.ok'));
    }

    /**
     * Detalle del hilo con sus mensajes (marca los del cliente como leídos).
     */
    public function show(Request $request, Hilo $hilo): JsonResponse
    {
        $this->authorize('view', $hilo);

        $hilo = $this->mensajes->detalle($hilo, $request->user());

        return ApiResponse::success((new HiloResource($hilo))->resolve($request), __('messages.ok'));
    }

    /**
     * Envía un mensaje al hilo (con adjunto opcional).
     */
    public function store(StoreMensajeRequest $request, Hilo $hilo): JsonResponse
    {
        $this->authorize('responder', $hilo);

        $mensaje = $this->mensajes->enviar(
            $hilo,
            $request->user(),
            (string) $request->validated('cuerpo'),
            $request->file('adjunto'),
        );

        return ApiResponse::created((new MensajeResource($mensaje))->resolve($request), __('messages.mensaje_enviado'));
    }

    /**
     * Cierra el hilo (el cliente ya no puede responder).
     */
    public function cerrar(Request $request, Hilo $hilo): JsonResponse
    {
        $this->authorize('cerrar', $hilo);

        $hilo = $this->mensajes->cambiarEstado($hilo, true, $request->user());

        return ApiResponse::success((new HiloResource($hilo))->resolve($request), __('messages.hilo_cerrado'));
    }

    /**
     * Reabre un hilo cerrado.
     */
    public function reabrir(Request $request, Hilo $hilo): JsonResponse
    {
        $this->authorize('reabrir', $hilo);

        $hilo = $this->mensajes->cambiarEstado($hilo, false, $request->user());

        return ApiResponse::success((new HiloResource($hilo))->resolve($request), __('messages.actualizado'));
    }
}
