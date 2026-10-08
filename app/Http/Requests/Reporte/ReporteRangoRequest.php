<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporte;

use App\Http\Requests\ApiFormRequest;

/**
 * Filtro de fechas de los reportes (`desde`/`hasta`), con límites de rango.
 */
class ReporteRangoRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'limite' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hasta.after_or_equal' => 'La fecha "hasta" debe ser posterior a "desde".',
        ];
    }

    /**
     * Fechas normalizadas (por defecto últimos 30 días).
     *
     * @return array{0: string|null, 1: string|null}
     */
    public function fechas(): array
    {
        return [
            $this->validated('desde'),
            $this->validated('hasta'),
        ];
    }
}
