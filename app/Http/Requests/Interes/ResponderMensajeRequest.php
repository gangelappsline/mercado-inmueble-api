<?php

declare(strict_types=1);

namespace App\Http\Requests\Interes;

use App\Http\Requests\ApiFormRequest;

/**
 * Respuesta del anunciante a un interesado
 * (`POST /api/v1/{panel}/interesados/{id}/responder`).
 */
class ResponderMensajeRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cuerpo' => ['required', 'string', 'min:2', 'max:5000'],
            'adjunto' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'cerrar_interes' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cuerpo.required' => 'Escribe la respuesta para el cliente.',
            'adjunto.mimes' => 'El adjunto debe ser una imagen (JPG, PNG, WEBP) o un PDF.',
        ];
    }
}
