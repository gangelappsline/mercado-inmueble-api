<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\InmobiliariaResource;
use App\Http\Resources\PropiedadResource;
use App\Models\Inmobiliaria;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Directorio público de inmobiliarias y vendedores anunciantes.
 */
class InmobiliariaController extends Controller
{
    public function __construct(
        private readonly PropiedadRepositoryInterface $propiedades,
    ) {
    }

    /**
     * Lista las inmobiliarias con publicaciones activas.
     */
    public function index(Request $request): JsonResponse
    {
        $porPagina = max(1, min((int) $request->integer('per_page', 15), (int) config('mercado.paginacion.maxima', 100)));
        $busqueda = $request->string('q')->trim()->value();

        $paginado = Inmobiliaria::query()
            ->with('user:id,name,email,phone')
            ->withCount(['propiedades' => fn ($query) => $query->publicadas()])
            ->when($busqueda !== '', fn ($query) => $query->where(function ($query) use ($busqueda): void {
                $comodin = '%'.$busqueda.'%';
                $query->where('razon_social', 'like', $comodin)
                    ->orWhere('nombre_comercial', 'like', $comodin)
                    ->orWhere('ciudad', 'like', $comodin);
            }))
            ->when($request->filled('ciudad'), fn ($query) => $query->where('ciudad', $request->string('ciudad')->value()))
            ->when($request->boolean('verificadas'), fn ($query) => $query->where('verificado', true))
            ->orderByDesc('verificado')
            ->orderByDesc('propiedades_count')
            ->paginate($porPagina)
            ->withQueryString();

        return ApiResponse::success(InmobiliariaResource::collection($paginado), __('messages.ok'));
    }

    /**
     * Ficha pública de la inmobiliaria con sus publicaciones.
     */
    public function show(Request $request, Inmobiliaria $inmobiliaria): JsonResponse
    {
        $inmobiliaria->load('user:id,name,email,phone');
        $inmobiliaria->loadCount(['propiedades' => fn ($query) => $query->publicadas()]);

        $publicaciones = $this->propiedades->listarParaPropietario(
            $inmobiliaria,
            ['solo_publicadas' => true],
            max(1, min((int) $request->integer('per_page', 12), (int) config('mercado.paginacion.maxima', 100))),
        );

        return ApiResponse::success([
            'inmobiliaria' => (new InmobiliariaResource($inmobiliaria))->resolve($request),
            'propiedades' => PropiedadResource::collection($publicaciones)->resolve($request),
        ], __('messages.ok'));
    }
}
