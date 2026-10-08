<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Publico\IndexPropiedadRequest;
use App\Http\Resources\PropiedadDetalleResource;
use App\Http\Resources\PropiedadResource;
use App\Models\Propiedad;
use App\Models\User;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use App\Services\PropiedadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo público de propiedades: búsqueda con filtros, destacadas y detalle
 * (cada consulta al detalle incrementa el contador de vistas).
 */
class PropiedadController extends Controller
{
    public function __construct(
        private readonly PropiedadRepositoryInterface $propiedades,
        private readonly PropiedadService $servicio,
    ) {
    }

    /**
     * Lista el catálogo con filtros, orden y paginación.
     */
    public function index(IndexPropiedadRequest $request): JsonResponse
    {
        $paginado = $this->propiedades->catalogar($request->filtros(), $request->porPagina());

        return ApiResponse::success(PropiedadResource::collection($paginado), __('messages.ok'));
    }

    /**
     * Propiedades destacadas para la portada.
     */
    public function destacadas(Request $request): JsonResponse
    {
        $limite = max(1, min((int) $request->integer('limite', 8), 20));

        return ApiResponse::success(
            PropiedadResource::collection($this->propiedades->destacadas($limite)),
            __('messages.ok'),
        );
    }

    /**
     * Detalle público de una propiedad publicada.
     */
    public function show(Request $request, Propiedad $propiedad): JsonResponse
    {
        if (! $propiedad->estado->esVisiblePublicamente()) {
            return ApiResponse::error(__('messages.propiedad_no_encontrada'), 404, 'NOT_FOUND');
        }

        /** @var User|null $usuario */
        $usuario = $request->user();
        $cliente = $usuario?->perfilCliente();

        $this->servicio->registrarVista($propiedad, $cliente);

        $propiedad = $this->servicio->detalle($propiedad);
        $propiedad->setRelation('similares', $this->propiedades->similares($propiedad));

        if ($cliente !== null) {
            $propiedad->es_favorito = $cliente->tieneFavorito($propiedad);
        }

        return ApiResponse::success((new PropiedadDetalleResource($propiedad))->resolve($request), __('messages.ok'));
    }
}
