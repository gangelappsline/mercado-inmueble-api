<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\EstadoPropiedad;
use App\Enums\OperacionPropiedad;
use App\Enums\TipoPropiedad;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de moderación del catálogo completo (`GET /admin/propiedades`).
 *
 * A diferencia del listado del panel, no se acota a un anunciante: permite
 * filtrar por estado (incluidos borradores, pausadas y rechazadas), por
 * anunciante y por los mismos criterios del catálogo público.
 */
class IndexPropiedadAdminRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:2', 'max:120'],
            'estado' => ['nullable', Rule::enum(EstadoPropiedad::class)],
            'tipo' => ['nullable', Rule::enum(TipoPropiedad::class)],
            'operacion' => ['nullable', Rule::enum(OperacionPropiedad::class)],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'estado_provincia' => ['nullable', 'string', 'max:120'],
            'destacada' => ['nullable', 'boolean'],
            'con_video' => ['nullable', 'boolean'],
            'propietario_tipo' => ['nullable', Rule::in(['inmobiliaria', 'vendedor'])],
            'propietario_id' => ['nullable', 'integer', 'min:1'],
            'orden' => ['nullable', Rule::in(['recientes', 'antiguas', 'precio_asc', 'precio_desc', 'area_desc', 'vistas', 'relevancia'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('mercado.paginacion.maxima', 100)],
        ];
    }

    /**
     * Filtros normalizados para el repositorio.
     *
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        return collect($this->validated())
            ->except(['page', 'per_page'])
            ->reject(static fn (mixed $valor): bool => $valor === null || $valor === '')
            ->all();
    }
}
