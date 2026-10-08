<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Hilo de conversación entre cliente y anunciante. Los contadores de no leídos
 * se exponen según el rol de quien consulta.
 *
 * @mixin \App\Models\Hilo
 */
class HiloResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rol = $request->user()?->role;

        return [
            'id' => $this->id,
            'asunto' => $this->asunto,
            'cerrado' => (bool) $this->cerrado,
            'total_mensajes' => $this->total_mensajes,
            'no_leidos' => $this->noLeidosSegunRol($rol),
            'no_leidos_cliente' => $this->no_leidos_cliente,
            'no_leidos_propietario' => $this->no_leidos_propietario,
            'ultimo_mensaje_en' => $this->ultimo_mensaje_en?->toIso8601String(),
            'interes_id' => $this->interes_id,
            'propiedad' => $this->when(
                $this->relationLoaded('interes') && $this->interes?->relationLoaded('propiedad'),
                fn (): ?array => $this->interes?->propiedad !== null
                    ? (new PropiedadResource($this->interes->propiedad))->resolve($request)
                    : null,
            ),
            'ultimo_mensaje' => new MensajeResource($this->whenLoaded('ultimoMensaje')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Mensajes sin leer para el rol que consulta la bandeja.
     */
    private function noLeidosSegunRol(?Role $rol): int
    {
        return match ($rol) {
            Role::Cliente => (int) $this->no_leidos_cliente,
            Role::Inmobiliaria, Role::Vendedor => (int) $this->no_leidos_propietario,
            default => 0,
        };
    }
}
