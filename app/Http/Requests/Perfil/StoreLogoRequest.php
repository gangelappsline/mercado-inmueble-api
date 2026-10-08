<?php

declare(strict_types=1);

namespace App\Http\Requests\Perfil;

use App\Http\Requests\ApiFormRequest;

/**
 * Subida del logotipo de la inmobiliaria o de la foto del vendedor.
 */
class StoreLogoRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $media = config('mercado.media');

        return [
            'logo' => [
                'required',
                'image',
                'mimes:'.implode(',', $media['mimes_foto']),
                'max:'.(int) $media['max_peso_foto_kb'],
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'Adjunta el archivo de imagen.',
            'logo.image' => 'El logotipo debe ser una imagen.',
            'logo.dimensions' => 'La imagen debe medir entre 100x100 y 4000x4000 píxeles.',
        ];
    }
}
