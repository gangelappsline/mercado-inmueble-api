<?php

declare(strict_types=1);

namespace App\Http\Requests\Propiedad;

use App\Enums\Moneda;
use App\Enums\OperacionPropiedad;
use App\Enums\TipoPropiedad;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de una propiedad con fotos (y opcionalmente video).
 */
class StorePropiedadRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $media = config('mercado.media');
        $maxAnio = (int) date('Y') + 1;

        return [
            // Datos principales
            'titulo' => ['required', 'string', 'min:10', 'max:200'],
            'descripcion' => ['required', 'string', 'min:30', 'max:5000'],
            'tipo' => ['required', Rule::enum(TipoPropiedad::class)],
            'operacion' => ['required', Rule::enum(OperacionPropiedad::class)],
            'precio' => ['required', 'numeric', 'min:1', 'max:99999999999'],
            'moneda' => ['required', Rule::enum(Moneda::class)],
            'expensas' => ['nullable', 'numeric', 'min:0'],
            'precio_negociable' => ['nullable', 'boolean'],
            'destacada' => ['nullable', 'boolean', Rule::prohibitedIf(fn (): bool => $this->user()?->esVendedor() ?? false)],

            // Superficies y ambientes
            'area_total' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'area_construida' => ['nullable', 'numeric', 'min:0', 'lte:area_total'],
            'habitaciones' => ['nullable', 'integer', 'between:0,30'],
            'banos' => ['nullable', 'integer', 'between:0,30'],
            'estacionamientos' => ['nullable', 'integer', 'between:0,50'],
            'piso' => ['nullable', 'integer', 'between:-5,120'],
            'anio_construccion' => ['nullable', 'integer', 'between:1800,'.$maxAnio],
            'amoblado' => ['nullable', 'boolean'],

            // Ubicación
            'direccion' => ['required', 'string', 'max:255'],
            'ciudad' => ['required', 'string', 'max:120'],
            'estado_provincia' => ['required', 'string', 'max:120'],
            'pais' => ['nullable', 'string', 'max:80'],
            'codigo_postal' => ['nullable', 'string', 'max:20'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],

            // Amenidades y medios
            'amenidades' => ['nullable', 'array', 'max:'.(int) config('mercado.reglas.max_amenidades_por_propiedad', 25)],
            'amenidades.*' => ['integer', 'distinct', 'exists:amenidades,id'],
            'fotos' => ['nullable', 'array', 'max:'.(int) $media['max_fotos_por_subida']],
            'fotos.*' => ['image', 'mimes:'.implode(',', $media['mimes_foto']), 'max:'.(int) $media['max_peso_foto_kb']],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:'.(int) $media['max_peso_video_kb']],

            'publicar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titulo.min' => 'El título debe describir la propiedad (mínimo 10 caracteres).',
            'descripcion.min' => 'La descripción debe tener al menos 30 caracteres.',
            'area_construida.lte' => 'El área construida no puede superar el área total.',
            'fotos.*.max' => 'Cada foto puede pesar como máximo :max KB.',
            'video.max' => 'El video no puede superar el tamaño máximo permitido.',
            'destacada.prohibited' => 'Sólo las inmobiliarias pueden destacar publicaciones.',
            'latitud.required_with' => 'Debes enviar latitud y longitud juntas.',
        ];
    }

    /**
     * Datos para el servicio (sin los archivos, que se envían aparte).
     *
     * @return array<string, mixed>
     */
    public function datosDeLaPropiedad(): array
    {
        return $this->datosValidados([
            'titulo', 'descripcion', 'tipo', 'operacion', 'precio', 'moneda', 'expensas',
            'precio_negociable', 'destacada', 'area_total', 'area_construida', 'habitaciones',
            'banos', 'estacionamientos', 'piso', 'anio_construccion', 'amoblado', 'direccion',
            'ciudad', 'estado_provincia', 'pais', 'codigo_postal', 'latitud', 'longitud',
        ]) + ['amenidades' => $this->input('amenidades', [])];
    }
}
