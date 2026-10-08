<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Api\Panel\Concerns\ResuelvePropietario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cita\CancelarCitaRequest;
use App\Http\Requests\Cita\IndexCitaRequest;
use App\Http\Requests\Cita\ReprogramarCitaRequest;
use App\Http\Resources\CitaResource;
use App\Models\Cita;
use App\Services\CitaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agenda del anunciante: confirmación, reprogramación, cancelación y cierre
 * de las visitas solicitadas por los clientes.
 */
class CitaController extends Controller
{
    use ResuelvePropietario;

    public function __construct(
        private readonly CitaService $citas,
    ) {
    }

    /**
     * Citas del anunciante con filtros por estado y rango de fechas.
     */
    public function index(IndexCitaRequest $request): JsonResponse
    {
        $paginado = $this->citas->listarParaPropietario(
            $this->propietario($request),
            $request->filtros(),
            $request->porPagina(),
        );

        return ApiResponse::success(CitaResource::collection($paginado), __('messages.ok'));
    }

    /**
     * Agenda de un día concreto (por defecto, hoy).
     */
    public function agenda(Request $request): JsonResponse
    {
        $fecha = $request->date('fecha')?->toDateString() ?? now()->toDateString();

        $citas = $this->citas->agendaDelDia($this->propietario($request), $fecha);

        return ApiResponse::success(
            CitaResource::collection($citas),
            __('messages.ok'),
            meta: ['fecha' => $fecha, 'total' => $citas->count()],
        );
    }

    /**
     * Detalle de una cita del anunciante.
     */
    public function show(Request $request, Cita $cita): JsonResponse
    {
        $this->authorize('view', $cita);

        return ApiResponse::success(
            (new CitaResource($cita->load(['propiedad.fotoPrincipal', 'cliente.user'])))->resolve($request),
            __('messages.ok'),
        );
    }

    /**
     * Confirma la solicitud de visita.
     */
    public function confirmar(Request $request, Cita $cita): JsonResponse
    {
        $this->authorize('confirmar', $cita);

        $cita = $this->citas->confirmar($cita, $request->user());

        return ApiResponse::success((new CitaResource($cita))->resolve($request), __('messages.cita_confirmada'));
    }

    /**
     * Reprograma la fecha y hora de la cita.
     */
    public function reprogramar(ReprogramarCitaRequest $request, Cita $cita): JsonResponse
    {
        $this->authorize('reprogramar', $cita);

        $cita = $this->citas->reprogramar($cita, $request->validated(), $request->user());

        return ApiResponse::success((new CitaResource($cita))->resolve($request), __('messages.cita_reprogramada'));
    }

    /**
     * Cancela la cita con un motivo opcional.
     */
    public function cancelar(CancelarCitaRequest $request, Cita $cita): JsonResponse
    {
        $this->authorize('cancelar', $cita);

        $cita = $this->citas->cancelar($cita, $request->user(), $request->validated('motivo'));

        return ApiResponse::success((new CitaResource($cita))->resolve($request), __('messages.cita_cancelada'));
    }

    /**
     * Marca la visita como realizada.
     */
    public function completar(Request $request, Cita $cita): JsonResponse
    {
        $this->authorize('completar', $cita);

        $cita = $this->citas->completar($cita, $request->user());

        return ApiResponse::success((new CitaResource($cita))->resolve($request), __('messages.cita_completada'));
    }

    /**
     * Marca que el cliente no asistió a la visita.
     */
    public function marcarNoAsistio(Request $request, Cita $cita): JsonResponse
    {
        $this->authorize('marcarNoAsistio', $cita);

        $cita = $this->citas->marcarNoAsistio($cita, $request->user());

        return ApiResponse::success((new CitaResource($cita))->resolve($request), __('messages.actualizado'));
    }
}
