<?php

declare(strict_types=1);

namespace App\Http\Requests\Cita;

use App\Enums\TipoCita;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Solicitud de cita del cliente (`POST /api/v1/cliente/citas`).
 */
class StoreCitaRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'propiedad_id' => ['required', 'integer', 'exists:propiedades,id'],
            'interes_id' => ['nullable', 'integer', 'exists:intereses,id'],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora' => ['required', 'date_format:H:i'],
            'duracion_minutos' => ['nullable', 'integer', 'between:'.(int) config('mercado.reglas.duracion_cita_minutos', 30).','.(int) config('mercado.reglas.duracion_cita_max_minutos', 240)],
            'tipo' => ['required', Rule::enum(TipoCita::class)],
            'lugar' => ['nullable', 'string', 'max:255', 'required_if:tipo,visita'],
            'enlace_virtual' => ['nullable', 'url', 'max:2048', 'required_if:tipo,virtual'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'propiedad_id.exists' => 'La propiedad indicada no existe.',
            'fecha.after_or_equal' => 'La fecha de la cita no puede ser anterior a hoy.',
            'hora.date_format' => 'La hora debe tener el formato HH:MM (24 horas).',
            'lugar.required_if' => 'Indica el lugar de la visita.',
            'enlace_virtual.required_if' => 'Las visitas virtuales requieren un enlace de videollamada.',
        ];
    }
}
