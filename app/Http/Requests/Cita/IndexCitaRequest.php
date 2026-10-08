<?php

declare(strict_types=1);

namespace App\Http\Requests\Cita;

use App\Enums\EstadoCita;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado de citas y de la agenda.
 */
class IndexCitaRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['nullable', Rule::enum(EstadoCita::class)],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'propiedad_id' => ['nullable', 'integer', 'exists:propiedades,id'],
            'proximas' => ['nullable', 'boolean'],
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
