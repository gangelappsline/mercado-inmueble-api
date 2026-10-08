<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporte;

use App\Enums\FormatoExportacion;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Exportación de reportes (`?formato=pdf|excel|csv`).
 */
class ExportarReporteRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'formato' => ['nullable', Rule::enum(FormatoExportacion::class)],
            'reporte' => ['nullable', Rule::in(['resumen', 'propiedades-mas-vistas', 'conversion', 'ingresos'])],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ];
    }

    /**
     * Formato solicitado (PDF por defecto, igual que en los documentos).
     */
    public function formato(): FormatoExportacion
    {
        $valor = $this->validated('formato');

        return $valor !== null
            ? FormatoExportacion::from((string) $valor)
            : FormatoExportacion::Pdf;
    }

    /**
     * Reporte a exportar (resumen general por defecto).
     */
    public function reporte(): string
    {
        return (string) ($this->validated('reporte') ?? 'resumen');
    }
}
