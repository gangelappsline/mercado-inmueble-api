<?php

declare(strict_types=1);

namespace App\Http\Requests\Propiedad;

use App\Enums\Moneda;
use App\Enums\OperacionPropiedad;
use App\Enums\TipoPropiedad;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Actualización parcial de una propiedad (PUT/PATCH).
 *
 * El estado no se modifica aquí: tiene endpoints dedicados
 * (`/publicar`, `/pausar`) para dejar traza de auditoría.
 */
class UpdatePropiedadRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxAnio = (int) date('Y') + 1;

        return [
            'titulo' => ['sometimes', 'string', 'min:10', 'max:200'],
            'descripcion' => ['sometimes', 'string', 'min:30', 'max:5000'],
            'tipo' => ['sometimes', Rule::enum(TipoPropiedad::class)],
            'operacion' => ['sometimes', Rule::enum(OperacionPropiedad::class)],
            'precio' => ['sometimes', 'numeric', 'min:1'],
            'moneda' => ['sometimes', Rule::enum(Moneda::class)],
            'expensas' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'precio_negociable' => ['sometimes', 'boolean'],
            'destacada' => ['sometimes', 'boolean', Rule::prohibitedIf(fn (): bool => $this->user()?->esVendedor() ?? false)],

            'area_total' => ['sometimes', 'numeric', 'min:1'],
            'area_construida' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'habitaciones' => ['sometimes', 'integer', 'between:0,30'],
            'banos' => ['sometimes', 'integer', 'between:0,30'],
            'estacionamientos' => ['sometimes', 'integer', 'between:0,50'],
            'piso' => ['sometimes', 'nullable', 'integer', 'between:-5,120'],
            'anio_construccion' => ['sometimes', 'nullable', 'integer', 'between:1800,'.$maxAnio],
            'amoblado' => ['sometimes', 'boolean'],

            'direccion' => ['sometimes', 'string', 'max:255'],
            'ciudad' => ['sometimes', 'string', 'max:120'],
            'estado_provincia' => ['sometimes', 'string', 'max:120'],
            'pais' => ['sometimes', 'nullable', 'string', 'max:80'],
            'codigo_postal' => ['sometimes', 'nullable', 'string', 'max:20'],
            'latitud' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],

            'amenidades' => ['sometimes', 'array'],
            'amenidades.*' => ['integer', 'distinct', 'exists:amenidades,id'],

            'estado' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.prohibited' => 'Usa los endpoints /publicar y /pausar para cambiar el estado.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDeLaPropiedad(): array
    {
        return $this->validated();
    }
}
