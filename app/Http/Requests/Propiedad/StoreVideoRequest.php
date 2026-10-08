<?php

declare(strict_types=1);

namespace App\Http\Requests\Propiedad;

use App\Http\Requests\ApiFormRequest;

/**
 * Subida del video de la propiedad: el límite de UN video por propiedad se
 * valida en `PropiedadService`/`MediaService` (devuelve 409 si ya existe).
 */
class StoreVideoRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $media = config('mercado.media');

        return [
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/webm,video/x-m4v',
                'max:'.(int) $media['max_peso_video_kb'],
            ],
            'reemplazar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'video.required' => 'Adjunta el archivo de video.',
            'video.mimetypes' => 'El video debe ser MP4, MOV o WEBM.',
            'video.max' => 'El video supera el tamaño máximo permitido (:max KB).',
        ];
    }
}
