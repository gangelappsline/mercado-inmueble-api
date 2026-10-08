<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cliente;

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
 * Mensajes del cliente: hilos con los anunciantes y respuestas.
 */
class MensajeController extends Controller
{
    public function __construct(
        private readonly MensajeService $mensajes,
    ) {
    }

    /**
     * Bandeja de hilos del cliente.
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
     * Detalle del hilo con sus mensajes (marca los del anunciante como leídos).
     */
    public function show(Request $request, Hilo $hilo): JsonResponse
    {
        $this->authorize('view', $hilo);

        return ApiResponse::success(
            (new HiloResource($this->mensajes->detalle($hilo, $request->user())))->resolve($request),
            __('messages.ok'),
        );
    }

    /**
     * Responde en el hilo (con adjunto opcional).
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
}
