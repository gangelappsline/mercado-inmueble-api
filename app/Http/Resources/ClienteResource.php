<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Perfil del cliente con su presupuesto y preferencias de búsqueda.
 *
 * @mixin \App\Models\Cliente
 */
class ClienteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'telefono' => $this->telefono,
            'telefono_alternativo' => $this->telefono_alternativo,
            'presupuesto_min' => $this->presupuesto_min !== null ? (float) $this->presupuesto_min : null,
            'presupuesto_max' => $this->presupuesto_max !== null ? (float) $this->presupuesto_max : null,
            'moneda' => $this->moneda?->value,
            'rango_presupuesto' => $this->rangoPresupuesto(),
            'tipo_propiedad_interes' => $this->tipo_propiedad_interes?->value,
            'ciudad_interes' => $this->ciudad_interes,
            'habitaciones_min' => $this->habitaciones_min,
            'preferencias' => $this->preferencias ?? [],
            'recibe_novedades' => (bool) $this->recibe_novedades,
            'acepta_terminos' => (bool) $this->acepta_terminos,
            'favoritos_count' => $this->whenCounted('favoritos'),
            'intereses_count' => $this->whenCounted('intereses'),
            'usuario' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
