<?php

declare(strict_types=1);

namespace App\Http\Requests\Publico;

use App\Http\Requests\ApiFormRequest;

/**
 * Formulario público de contacto.
 */
class StoreContactoRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:180'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'asunto' => ['required', 'string', 'min:4', 'max:200'],
            'mensaje' => ['required', 'string', 'min:20', 'max:3000'],
            'acepta_politica' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mensaje.min' => 'Cuéntanos un poco más: el mensaje debe tener al menos 20 caracteres.',
        ];
    }
}
