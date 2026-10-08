<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAnuncianteRequest;
use App\Http\Resources\InmobiliariaResource;
use App\Models\Inmobiliaria;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestión administrativa de inmobiliarias (`/admin/inmobiliarias`): directorio
 * completo (verificadas y pendientes), verificación de la cuenta y métricas.
 *
 * La suspensión o reactivación de la cuenta de acceso se hace desde
 * `/admin/usuarios/{id}/activar|desactivar`.
 */
class InmobiliariaController extends Controller
{
    public function __construct(
        private readonly AdministracionService $administracion,
        private readonly PropiedadRepositoryInterface $propiedades,
    ) {
    }

    /**
     * Listado de inmobiliarias con filtros (búsqueda, ciudad, verificación).
     */
    public function index(IndexAnuncianteRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Inmobiliaria::class);

        $paginado = $this->administracion->listarInmobiliarias($request->filtros(), $request->porPagina());

        return ApiResponse::success(InmobiliariaResource::collection($paginado), __('messages.ok'));
    }

    /**
     * Ficha administrativa con el resumen de sus publicaciones.
     */
    public function show(Request $request, Inmobiliaria $inmobiliaria): JsonResponse
    {
        $this->authorize('gestionar', $inmobiliaria);

        $inmobiliaria->load('user:id,name,email,phone,role,is_active,last_login_at');
        $inmobiliaria->loadCount('propiedades');

        return ApiResponse::success([
            'inmobiliaria' => (new InmobiliariaResource($inmobiliaria))->resolve($request),
            'resumen' => [
                'publicaciones' => $this->propiedades->resumenPorEstado($inmobiliaria),
                'citas' => $inmobiliaria->citas()->count(),
                'interesados' => $inmobiliaria->intereses()->count(),
            ],
        ], __('messages.ok'));
    }

    /**
     * Verifica la inmobiliaria (insignia de anunciante confiable).
     */
    public function verificar(Request $request, Inmobiliaria $inmobiliaria): JsonResponse
    {
        $this->authorize('verificar', $inmobiliaria);

        $perfil = $this->administracion->verificarInmobiliaria($inmobiliaria, true);

        return ApiResponse::success(
            ['inmobiliaria' => new InmobiliariaResource($perfil)],
            __('messages.admin_verificacion_otorgada'),
        );
    }

    /**
     * Retira la verificación otorgada.
     */
    public function retirarVerificacion(Request $request, Inmobiliaria $inmobiliaria): JsonResponse
    {
        $this->authorize('verificar', $inmobiliaria);

        $perfil = $this->administracion->verificarInmobiliaria($inmobiliaria, false);

        return ApiResponse::success(
            ['inmobiliaria' => new InmobiliariaResource($perfil)],
            __('messages.admin_verificacion_retirada'),
        );
    }
}
