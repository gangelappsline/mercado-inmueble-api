<?php

declare(strict_types=1);

namespace App\Http\Requests\Propiedad;

use App\Enums\EstadoPropiedad;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado del panel (`/inmobiliaria/propiedades`, `/vendedor/...`).
 */
class IndexPropiedadPanelRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:2', 'max:120'],
            'estado' => ['nullable', Rule::enum(EstadoPropiedad::class)],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'solo_publicadas' => ['nullable', 'boolean'],
            'orden' => ['nullable', Rule::in(['recientes', 'antiguas', 'precio_asc', 'precio_desc', 'area_desc', 'vistas', 'relevancia'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('mercado.paginacion.maxima', 100)],
        ];
    }

    /**
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
