<?php

declare(strict_types=1);

namespace App\Http\Requests\Cita;

use App\Http\Requests\ApiFormRequest;

/**
 * Cancelación de una cita.
 */
class CancelarCitaRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'min:5', 'max:1000'],
        ];
    }
}
