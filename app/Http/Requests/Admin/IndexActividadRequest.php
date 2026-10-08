<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

/**
 * Filtros de la bitácora de auditoría (`GET /admin/actividad`).
 *
 * `subject_type` acepta el valor guardado por el auditor: el alias del mapa
 * polimórfico para las entidades registradas en él (`user`, `inmobiliaria`,
 * `vendedor`, `cliente`) y el FQCN para el resto (`App\Models\Propiedad`, …).
 */
class IndexActividadRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'min:2', 'max:180'],
            'log_name' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:100'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'causer_id' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('mercado.paginacion.maxima', 100)],
        ];
    }

    /**
     * Filtros normalizados para el servicio.
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
