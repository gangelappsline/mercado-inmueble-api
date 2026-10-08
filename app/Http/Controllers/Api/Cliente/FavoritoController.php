<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cliente;

use App\Http\Controllers\Api\Cliente\Concerns\ResuelveCliente;
use App\Http\Controllers\Controller;
use App\Http\Requests\Favorito\FavoritoRequest;
use App\Http\Resources\PropiedadResource;
use App\Models\Favorito;
use App\Models\Propiedad;
use App\Services\FavoritoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Favoritos del cliente: guardar, listar, anotar y quitar propiedades.
 */
class FavoritoController extends Controller
{
    use ResuelveCliente;

    public function __construct(
        private readonly FavoritoService $favoritos,
    ) {
    }

    /**
     * Lista paginada de favoritos con la tarjeta de cada propiedad.
     */
    public function index(Request $request): JsonResponse
    {
        $paginado = $this->favoritos->listar(
            $this->cliente($request),
            max(1, min((int) $request->integer('per_page', 15), (int) config('mercado.paginacion.maxima', 100))),
        );

        return ApiResponse::success(
            PropiedadResource::collection($paginado->through(fn (Favorito $favorito): Propiedad => $favorito->propiedad)),
            __('messages.ok'),
        );
    }

    /**
     * Guarda una propiedad en favoritos (409 si ya estaba guardada).
     */
    public function store(FavoritoRequest $request): JsonResponse
    {
        $this->authorize('create', Favorito::class);

        $propiedad = Propiedad::query()->findOrFail((int) $request->validated('propiedad_id'));

        $favorito = $this->favoritos->agregar(
            $this->cliente($request),
            $propiedad,
            $request->validated('nota'),
        );

        return ApiResponse::created([
            'id' => $favorito->getKey(),
            'propiedad_id' => $favorito->propiedad_id,
            'nota' => $favorito->nota,
            'total_favoritos' => $propiedad->refresh()->favoritos_count,
        ], __('messages.favorito_agregado'));
    }

    /**
     * Actualiza la nota privada del favorito.
     */
    public function update(FavoritoRequest $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('create', Favorito::class);

        $cliente = $this->cliente($request);
        $favorito = Favorito::query()
            ->where('cliente_id', $cliente->getKey())
            ->where('propiedad_id', $propiedad->getKey())
            ->firstOrFail();

        $favorito->update(['nota' => $request->validated('nota')]);

        return ApiResponse::success([
            'id' => $favorito->getKey(),
            'propiedad_id' => $favorito->propiedad_id,
            'nota' => $favorito->nota,
        ], __('messages.actualizado'));
    }

    /**
     * Quita la propiedad de favoritos.
     */
    public function destroy(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('create', Favorito::class);

        $this->favoritos->quitar($this->cliente($request), $propiedad);

        return ApiResponse::success(null, __('messages.favorito_eliminado'));
    }
}
