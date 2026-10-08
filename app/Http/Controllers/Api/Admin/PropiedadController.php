<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexPropiedadAdminRequest;
use App\Http\Requests\Admin\RechazarPropiedadRequest;
use App\Http\Resources\PropiedadDetalleResource;
use App\Http\Resources\PropiedadResource;
use App\Models\Propiedad;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Moderación del catálogo (`/admin/propiedades`).
 *
 * El agente ve **todas** las publicaciones de la plataforma (incluidos
 * borradores, pausadas y rechazadas) y puede destacarlas, publicarlas,
 * pausarlas, rechazarlas por incumplir las políticas del marketplace, darlas de
 * baja y restaurarlas.
 */
class PropiedadController extends Controller
{
    public function __construct(
        private readonly AdministracionService $administracion,
        private readonly PropiedadRepositoryInterface $repositorio,
    ) {
    }

    /**
     * Listado global con filtros de moderación y resumen por estado.
     */
    public function index(IndexPropiedadAdminRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Propiedad::class);

        $paginado = $this->administracion->listarPropiedades($request->filtros(), $request->porPagina());

        return ApiResponse::success(
            PropiedadResource::collection($paginado),
            __('messages.ok'),
            meta: ['resumen_por_estado' => $this->administracion->resumenPorEstado()],
        );
    }

    /**
     * Detalle completo de la publicación (medios, anunciante y contadores).
     */
    public function show(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('view', $propiedad);

        return ApiResponse::success(
            (new PropiedadDetalleResource($this->repositorio->detalle($propiedad)))->resolve($request),
            __('messages.ok'),
        );
    }

    /**
     * Destaca la publicación en la portada del catálogo.
     */
    public function destacar(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('destacar', $propiedad);

        $moderada = $this->administracion->destacar($propiedad, true);

        return ApiResponse::success(
            (new PropiedadResource($moderada))->resolve($request),
            __('messages.admin_propiedad_destacada'),
        );
    }

    /**
     * Retira el destacado de la publicación.
     */
    public function retirarDestacado(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('destacar', $propiedad);

        $moderada = $this->administracion->destacar($propiedad, false);

        return ApiResponse::success(
            (new PropiedadResource($moderada))->resolve($request),
            __('messages.admin_destacado_retirado'),
        );
    }

    /**
     * Publica una publicación retenida (valida los requisitos mínimos).
     */
    public function publicar(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('moderar', $propiedad);

        $moderada = $this->administracion->publicar($propiedad);

        return ApiResponse::success(
            (new PropiedadResource($moderada))->resolve($request),
            __('messages.propiedad_publicada'),
        );
    }

    /**
     * Retira la publicación del catálogo sin eliminarla.
     */
    public function pausar(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('moderar', $propiedad);

        $moderada = $this->administracion->pausar($propiedad);

        return ApiResponse::success(
            (new PropiedadResource($moderada))->resolve($request),
            __('messages.propiedad_pausada'),
        );
    }

    /**
     * Rechaza la publicación por incumplir las políticas del marketplace.
     */
    public function rechazar(RechazarPropiedadRequest $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('moderar', $propiedad);

        $moderada = $this->administracion->rechazar($propiedad, $request->motivo());

        return ApiResponse::success(
            (new PropiedadResource($moderada))->resolve($request),
            __('messages.admin_propiedad_moderada'),
        );
    }

    /**
     * Baja administrativa de la publicación (elimina también sus medios).
     */
    public function destroy(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('moderar', $propiedad);

        $this->administracion->eliminarPropiedad($propiedad);

        return ApiResponse::success(null, __('messages.eliminado'));
    }

    /**
     * Restaura una publicación dada de baja.
     */
    public function restaurar(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('restore', $propiedad);

        $restaurada = $this->administracion->restaurarPropiedad($propiedad);

        return ApiResponse::success(
            (new PropiedadResource($restaurada))->resolve($request),
            __('messages.actualizado'),
        );
    }
}
