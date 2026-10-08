<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

/**
 * Rechazo administrativo de una publicación
 * (`POST /admin/propiedades/{propiedad}/rechazar`).
 */
class RechazarPropiedadRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Motivo del rechazo (queda en la auditoría de la plataforma).
     */
    public function motivo(): ?string
    {
        $motivo = $this->validated('motivo');

        return is_string($motivo) && trim($motivo) !== '' ? trim($motivo) : null;
    }
}
