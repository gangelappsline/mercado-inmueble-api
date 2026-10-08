<?php

declare(strict_types=1);

namespace App\Http\Requests\Interes;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro de interés del cliente sobre una propiedad
 * (`POST /api/v1/cliente/propiedades/{id}/interes`).
 */
class StoreInteresRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mensaje' => ['required', 'string', 'min:10', 'max:1500'],
            'telefono_contacto' => ['nullable', 'string', 'max:30'],
            'preferencia_contacto' => ['nullable', Rule::in(['email', 'telefono', 'whatsapp'])],
            'origen' => ['nullable', Rule::in(['web', 'app', 'telefono', 'whatsapp'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mensaje.required' => 'Escribe un mensaje para el anunciante.',
            'mensaje.min' => 'El mensaje debe tener al menos 10 caracteres.',
        ];
    }
}
