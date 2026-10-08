<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cliente;

use App\Http\Controllers\Api\Cliente\Concerns\ResuelveCliente;
use App\Http\Controllers\Controller;
use App\Http\Requests\Publico\IndexPropiedadRequest;
use App\Http\Resources\PropiedadDetalleResource;
use App\Http\Resources\PropiedadResource;
use App\Models\Propiedad;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use App\Services\PropiedadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Búsqueda personalizada del cliente: los filtros guardados en su perfil se
 * aplican como valores por defecto (y pueden sobreescribirse por query string).
 */
class PropiedadController extends Controller
{
    use ResuelveCliente;

    public function __construct(
        private readonly PropiedadRepositoryInterface $propiedades,
        private readonly PropiedadService $servicio,
    ) {
    }

    /**
     * Catálogo filtrado con las preferencias del cliente.
     */
    public function index(IndexPropiedadRequest $request): JsonResponse
    {
        $cliente = $this->cliente($request);
        $filtros = array_merge($cliente->preferenciasDeBusqueda(), $request->filtros());

        $paginado = $this->propiedades->catalogar($filtros, $request->porPagina());

        return ApiResponse::success(
            PropiedadResource::collection($paginado),
            __('messages.ok'),
            meta: ['preferencias_aplicadas' => $cliente->preferenciasDeBusqueda()],
        );
    }

    /**
     * Detalle de una propiedad publicada, con la marca de favorito del cliente.
     */
    public function show(Request $request, Propiedad $propiedad): JsonResponse
    {
        if (! $propiedad->estado->esVisiblePublicamente()) {
            return ApiResponse::error(__('messages.propiedad_no_encontrada'), 404, 'NOT_FOUND');
        }

        $cliente = $this->cliente($request);

        $this->servicio->registrarVista($propiedad, $cliente);

        $propiedad = $this->servicio->detalle($propiedad);
        $propiedad->setRelation('similares', $this->propiedades->similares($propiedad));
        $propiedad->es_favorito = $cliente->tieneFavorito($propiedad);

        return ApiResponse::success((new PropiedadDetalleResource($propiedad))->resolve($request), __('messages.ok'));
    }
}
