<?php

declare(strict_types=1);

namespace App\Http\Requests\Interes;

use App\Http\Requests\ApiFormRequest;

/**
 * Mensaje nuevo dentro de un hilo existente.
 */
class StoreMensajeRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cuerpo' => ['required', 'string', 'min:2', 'max:5000'],
            'adjunto' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }
}
