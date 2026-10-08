<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\AmenidadResource;
use App\Services\CatalogoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Catálogos públicos (cacheados) usados por los filtros y formularios del
 * frontend: amenidades, ciudades con publicaciones y opciones de enums.
 */
class CatalogoController extends Controller
{
    public function __construct(
        private readonly CatalogoService $catalogo,
    ) {
    }

    /**
     * Amenidades activas agrupables por categoría.
     */
    public function amenidades(): JsonResponse
    {
        return ApiResponse::success(AmenidadResource::collection($this->catalogo->amenidades()), __('messages.ok'));
    }

    /**
     * Ciudades con publicaciones activas y su conteo.
     */
    public function ciudades(): JsonResponse
    {
        return ApiResponse::success($this->catalogo->ciudades(), __('messages.ok'));
    }

    /**
     * Todas las opciones de los enums del dominio (para selects del frontend).
     */
    public function opciones(): JsonResponse
    {
        return ApiResponse::success($this->catalogo->opciones(), __('messages.ok'));
    }
}
