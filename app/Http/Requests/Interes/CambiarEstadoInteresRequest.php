<?php

declare(strict_types=1);

namespace App\Http\Requests\Interes;

use App\Enums\EstadoInteres;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio de estado del embudo comercial (atendido, cerrado, descartado).
 */
class CambiarEstadoInteresRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::enum(EstadoInteres::class)],
            'nota' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
