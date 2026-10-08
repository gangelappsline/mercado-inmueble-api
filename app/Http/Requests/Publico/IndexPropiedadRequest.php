<?php

declare(strict_types=1);

namespace App\Http\Requests\Publico;

use App\Enums\Moneda;
use App\Enums\OperacionPropiedad;
use App\Enums\TipoPropiedad;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del catálogo público (`GET /api/v1/propiedades`).
 *
 * Todos los parámetros son opcionales y combinables; `amenidades` acepta tanto
 * CSV (`amenidades=1,2,3`) como arreglo (`amenidades[]=1&amenidades[]=2`).
 */
class IndexPropiedadRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hoy = now()->toDateString();

        return [
            'q' => ['nullable', 'string', 'min:2', 'max:120'],
            'tipo' => ['nullable', Rule::enum(TipoPropiedad::class)],
            'operacion' => ['nullable', Rule::enum(OperacionPropiedad::class)],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'estado_provincia' => ['nullable', 'string', 'max:120'],
            'precio_min' => ['nullable', 'numeric', 'min:0'],
            'precio_max' => ['nullable', 'numeric', 'min:0', 'gte:precio_min'],
            'moneda' => ['nullable', Rule::enum(Moneda::class)],
            'habitaciones_min' => ['nullable', 'integer', 'between:0,30'],
            'banos_min' => ['nullable', 'integer', 'between:0,30'],
            'estacionamientos_min' => ['nullable', 'integer', 'between:0,50'],
            'area_min' => ['nullable', 'numeric', 'min:0'],
            'destacada' => ['nullable', 'boolean'],
            'con_video' => ['nullable', 'boolean'],
            'amenidades' => ['nullable'],
            'amenidades.*' => ['integer', 'exists:amenidades,id'],
            'amenidades_todas' => ['nullable', 'boolean'],

            // Geolocalización: `cerca_de=-16.5,-68.15` o latitud/longitud
            'cerca_de' => ['nullable', 'string', 'regex:/^-?\d{1,3}(\.\d+)?,-?\d{1,3}(\.\d+)?$/'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
            'radio_km' => ['nullable', 'numeric', 'min:0.1', 'max:'.(int) config('mercado.geo.radio_max_km', 100)],

            'orden' => ['nullable', Rule::in(['recientes', 'antiguas', 'precio_asc', 'precio_desc', 'area_desc', 'vistas', 'relevancia', 'distancia'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('mercado.paginacion.maxima', 100)],
            '_' => ['nullable', 'string', 'max:40', 'date_format:Y-m-d', 'before_or_equal:'.$hoy],
        ];
    }

    /**
     * Mensajes de error específicos del catálogo.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'precio_max.gte' => 'El precio máximo debe ser mayor o igual al precio mínimo.',
            'cerca_de.regex' => 'El formato de `cerca_de` es "latitud,longitud" (por ejemplo -16.50,-68.15).',
            'radio_km.max' => 'El radio de búsqueda supera el máximo permitido.',
        ];
    }

    /**
     * Filtros listos para el repositorio (sólo los permitidos y no vacíos).
     *
     * @return array<string, mixed>
     */
    public function filtros(): array
    {
        return collect($this->validated())
            ->except(['page', 'per_page', '_'])
            ->reject(static fn (mixed $valor): bool => $valor === null || $valor === '' || $valor === [])
            ->all();
    }
}
