<?php

declare(strict_types=1);

namespace App\Http\Requests\Interes;

use App\Enums\EstadoInteres;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros de la bandeja de interesados del panel.
 */
class IndexInteresRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['nullable', Rule::enum(EstadoInteres::class)],
            'propiedad_id' => ['nullable', 'integer', 'exists:propiedades,id'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'solo_nuevos' => ['nullable', 'boolean'],
            'orden' => ['nullable', 'in:recientes,antiguos'],
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
