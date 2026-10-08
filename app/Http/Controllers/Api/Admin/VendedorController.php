<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAnuncianteRequest;
use App\Http\Resources\VendedorResource;
use App\Models\Vendedor;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestión administrativa de vendedores (`/admin/vendedores`): directorio
 * completo (verificados y pendientes), verificación de la cuenta y métricas.
 *
 * La suspensión o reactivación de la cuenta de acceso se hace desde
 * `/admin/usuarios/{id}/activar|desactivar`.
 */
class VendedorController extends Controller
{
    public function __construct(
        private readonly AdministracionService $administracion,
        private readonly PropiedadRepositoryInterface $propiedades,
    ) {
    }

    /**
     * Listado de vendedores con filtros (búsqueda, ciudad, verificación).
     */
    public function index(IndexAnuncianteRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Vendedor::class);

        $paginado = $this->administracion->listarVendedores($request->filtros(), $request->porPagina());

        return ApiResponse::success(VendedorResource::collection($paginado), __('messages.ok'));
    }

    /**
     * Ficha administrativa con el resumen de sus publicaciones.
     */
    public function show(Request $request, Vendedor $vendedor): JsonResponse
    {
        $this->authorize('gestionar', $vendedor);

        $vendedor->load('user:id,name,email,phone,role,is_active,last_login_at');
        $vendedor->loadCount('propiedades');

        return ApiResponse::success([
            'vendedor' => (new VendedorResource($vendedor))->resolve($request),
            'resumen' => [
                'publicaciones' => $this->propiedades->resumenPorEstado($vendedor),
                'citas' => $vendedor->citas()->count(),
                'interesados' => $vendedor->intereses()->count(),
            ],
        ], __('messages.ok'));
    }

    /**
     * Verifica al vendedor (identidad y documentación revisadas).
     */
    public function verificar(Request $request, Vendedor $vendedor): JsonResponse
    {
        $this->authorize('verificar', $vendedor);

        $perfil = $this->administracion->verificarVendedor($vendedor, true);

        return ApiResponse::success(
            ['vendedor' => new VendedorResource($perfil)],
            __('messages.admin_verificacion_otorgada'),
        );
    }

    /**
     * Retira la verificación otorgada.
     */
    public function retirarVerificacion(Request $request, Vendedor $vendedor): JsonResponse
    {
        $this->authorize('verificar', $vendedor);

        $perfil = $this->administracion->verificarVendedor($vendedor, false);

        return ApiResponse::success(
            ['vendedor' => new VendedorResource($perfil)],
            __('messages.admin_verificacion_retirada'),
        );
    }
}
