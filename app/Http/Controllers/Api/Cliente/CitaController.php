<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cliente;

use App\Http\Controllers\Api\Cliente\Concerns\ResuelveCliente;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cita\CancelarCitaRequest;
use App\Http\Requests\Cita\IndexCitaRequest;
use App\Http\Requests\Cita\ReprogramarCitaRequest;
use App\Http\Requests\Cita\StoreCitaRequest;
use App\Http\Resources\CitaResource;
use App\Models\Cita;
use App\Models\Interes;
use App\Models\Propiedad;
use App\Services\CitaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Citas del cliente: solicitud de visitas y gestión de las propias.
 */
class CitaController extends Controller
{
    use ResuelveCliente;

    public function __construct(
        private readonly CitaService $citas,
    ) {
    }

    /**
     * Citas solicitadas por el cliente.
     */
    public function index(IndexCitaRequest $request): JsonResponse
    {
        $paginado = $this->citas->listarParaCliente(
            $this->cliente($request),
            $request->filtros(),
            $request->porPagina(),
        );

        return ApiResponse::success(CitaResource::collection($paginado), __('messages.ok'));
    }

    /**
     * Solicita una cita sobre una propiedad publicada.
     */
    public function store(StoreCitaRequest $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('create', Cita::class);

        $datos = $request->validated();
        $interes = null;

        if (filled($datos['interes_id'] ?? null)) {
            $interes = Interes::query()->find((int) $datos['interes_id']);
        }

        $cita = $this->citas->solicitar($propiedad, $this->cliente($request), $datos, $interes);

        return ApiResponse::created((new CitaResource($cita))->resolve($request), __('messages.cita_solicitada'));
    }

    /**
     * Detalle de una cita propia.
     */
    public function show(Request $request, Cita $cita): JsonResponse
    {
        $this->authorize('view', $cita);

        return ApiResponse::success(
            (new CitaResource($cita->load(['propiedad.fotoPrincipal', 'propietario'])))->resolve($request),
            __('messages.ok'),
        );
    }

    /**
     * Reprograma una cita propia (mientras no sea final).
     */
    public function reprogramar(ReprogramarCitaRequest $request, Cita $cita): JsonResponse
    {
        $this->authorize('reprogramar', $cita);

        $cita = $this->citas->reprogramar($cita, $request->validated(), $request->user());

        return ApiResponse::success((new CitaResource($cita))->resolve($request), __('messages.cita_reprogramada'));
    }

    /**
     * Cancela una cita propia con motivo opcional.
     */
    public function cancelar(CancelarCitaRequest $request, Cita $cita): JsonResponse
    {
        $this->authorize('cancelar', $cita);

        $cita = $this->citas->cancelar($cita, $request->user(), $request->validated('motivo'));

        return ApiResponse::success((new CitaResource($cita))->resolve($request), __('messages.cita_cancelada'));
    }
}
