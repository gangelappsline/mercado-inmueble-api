<?php

declare(strict_types=1);

namespace App\Http\Requests\Propiedad;

use App\Http\Requests\ApiFormRequest;

/**
 * Subida de una o varias fotos para una propiedad existente.
 */
class StoreFotoRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $media = config('mercado.media');

        return [
            'fotos' => ['required', 'array', 'min:1', 'max:'.(int) $media['max_fotos_por_subida']],
            'fotos.*' => [
                'required',
                'image',
                'mimes:'.implode(',', $media['mimes_foto']),
                'max:'.(int) $media['max_peso_foto_kb'],
            ],
            'principal' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fotos.required' => 'Adjunta al menos una foto.',
            'fotos.max' => 'Puedes subir como máximo :max fotos por vez.',
            'fotos.*.mimes' => 'Las fotos deben ser JPG, PNG o WEBP.',
            'fotos.*.max' => 'Cada foto puede pesar como máximo :max KB.',
            'fotos.*.image' => 'El archivo :position no es una imagen válida.',
        ];
    }
}
