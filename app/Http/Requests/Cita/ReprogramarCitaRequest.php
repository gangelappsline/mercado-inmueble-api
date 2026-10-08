<?php

declare(strict_types=1);

namespace App\Http\Requests\Cita;

use App\Http\Requests\ApiFormRequest;

/**
 * Reprogramación de una cita existente (panel y cliente).
 */
class ReprogramarCitaRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora' => ['required', 'date_format:H:i'],
            'duracion_minutos' => ['nullable', 'integer', 'between:'.(int) config('mercado.reglas.duracion_cita_minutos', 30).','.(int) config('mercado.reglas.duracion_cita_max_minutos', 240)],
            'motivo' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.after_or_equal' => 'La nueva fecha no puede ser anterior a hoy.',
        ];
    }
}
