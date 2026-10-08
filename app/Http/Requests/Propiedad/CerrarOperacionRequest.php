<?php

declare(strict_types=1);

namespace App\Http\Requests\Propiedad;

use App\Enums\EstadoPropiedad;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Cierre de la operación de una publicación (vendida o alquilada).
 */
class CerrarOperacionRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in([EstadoPropiedad::Vendida->value, EstadoPropiedad::Alquilada->value])],
            'precio_final' => ['nullable', 'numeric', 'min:0'],
            'nota' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Indica si la operación se cerró como venta o alquiler.',
            'estado.in' => 'El estado de cierre debe ser "vendida" o "alquilada".',
        ];
    }

    /**
     * Estado de cierre solicitado.
     */
    public function estadoDeCierre(): EstadoPropiedad
    {
        return EstadoPropiedad::from((string) $this->validated('estado'));
    }
}
