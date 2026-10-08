<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard de administración (`/admin/dashboard`): métricas globales de la
 * plataforma — cuentas por rol, anunciantes pendientes de verificación,
 * catálogo por estado, actividad comercial y comisión estimada.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly AdministracionService $administracion,
    ) {
    }

    /**
     * Resumen ejecutivo de la plataforma.
     */
    public function resumen(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return ApiResponse::success($this->administracion->resumen(), __('messages.ok'));
    }
}
