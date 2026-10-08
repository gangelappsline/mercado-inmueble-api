<?php

declare(strict_types=1);

namespace App\Http\Requests\Interes;

use App\Http\Requests\ApiFormRequest;

/**
 * Filtros de la bandeja de mensajes (hilos).
 */
class IndexHiloRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:2', 'max:120'],
            'solo_no_leidos' => ['nullable', 'boolean'],
            'estado' => ['nullable', 'in:abiertos,cerrados,todos'],
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
