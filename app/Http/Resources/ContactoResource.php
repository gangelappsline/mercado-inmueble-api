<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mensaje del formulario público de contacto (bandeja de administración).
 *
 * @mixin \App\Models\Contacto
 */
class ContactoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'telefono' => $this->telefono,
            'asunto' => $this->asunto,
            'mensaje' => $this->mensaje,
            'atendido' => (bool) $this->atendido,
            'atendido_en' => $this->atendido_en?->toIso8601String(),
            // Trazabilidad anti-spam: sólo visible en el panel de administración.
            'ip' => $this->when($request->user()?->esAdministrador() ?? false, fn (): ?string => $this->ip),
            'user_agent' => $this->when($request->user()?->esAdministrador() ?? false, fn (): ?string => $this->user_agent),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
