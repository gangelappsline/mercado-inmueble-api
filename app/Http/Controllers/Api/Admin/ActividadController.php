<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexActividadRequest;
use App\Http\Resources\ActividadResource;
use App\Models\User;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Bitácora de auditoría de la plataforma (`/admin/actividad`).
 *
 * Expone el `activity_log` de spatie/laravel-activitylog: cada acción
 * administrativa (altas, suspensiones, cambios de rol, verificaciones y
 * moderación de publicaciones) queda registrada con el agente que la ejecutó.
 */
class ActividadController extends Controller
{
    public function __construct(
        private readonly AdministracionService $administracion,
    ) {
    }

    /**
     * Listado de registros con filtros por evento, entidad, agente y fechas.
     */
    public function index(IndexActividadRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $paginado = $this->administracion->listarActividad($request->filtros(), $request->porPagina());

        return ApiResponse::success(ActividadResource::collection($paginado), __('messages.ok'));
    }

    /**
     * Detalle de un registro de auditoría.
     */
    public function show(Request $request, Activity $actividad): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $actividad->loadMissing('causer');

        return ApiResponse::success(
            (new ActividadResource($actividad))->resolve($request),
            __('messages.ok'),
        );
    }
}
