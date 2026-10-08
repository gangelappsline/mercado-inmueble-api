<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cliente;

use App\Http\Controllers\Api\Cliente\Concerns\ResuelveCliente;
use App\Http\Controllers\Controller;
use App\Http\Requests\Interes\IndexInteresRequest;
use App\Http\Requests\Interes\StoreInteresRequest;
use App\Http\Resources\InteresResource;
use App\Models\Interes;
use App\Models\Propiedad;
use App\Services\InteresService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Intereses del cliente: registra la consulta sobre una propiedad (dispara el
 * correo al anunciante) y lista el seguimiento propio.
 */
class InteresController extends Controller
{
    use ResuelveCliente;

    public function __construct(
        private readonly InteresService $intereses,
    ) {
    }

    /**
     * Intereses registrados por el cliente.
     */
    public function index(IndexInteresRequest $request): JsonResponse
    {
        $paginado = $this->intereses->listarParaCliente(
            $this->cliente($request),
            $request->filtros(),
            $request->porPagina(),
        );

        return ApiResponse::success(InteresResource::collection($paginado), __('messages.ok'));
    }

    /**
     * Registra el interés del cliente por una propiedad publicada.
     */
    public function store(StoreInteresRequest $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('create', Interes::class);

        $interes = $this->intereses->registrar(
            $propiedad,
            $this->cliente($request),
            $request->validated('mensaje'),
            (string) ($request->validated('origen') ?? 'web'),
        );

        return ApiResponse::created((new InteresResource($interes))->resolve($request), __('messages.interes_registrado'));
    }

    /**
     * Detalle de un interés propio con su hilo de conversación.
     */
    public function show(Request $request, Interes $interes): JsonResponse
    {
        $this->authorize('view', $interes);

        $interes->load(['propiedad.fotoPrincipal', 'propiedad.propietario', 'hilo.ultimoMensaje']);

        return ApiResponse::success((new InteresResource($interes))->resolve($request), __('messages.ok'));
    }

    /**
     * Retira el interés propio (borrado lógico).
     */
    public function destroy(Request $request, Interes $interes): JsonResponse
    {
        $this->authorize('delete', $interes);

        $interes->delete();

        return ApiResponse::success(null, __('messages.eliminado'));
    }
}
