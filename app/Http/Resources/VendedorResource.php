<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha del vendedor particular.
 *
 * @mixin \App\Models\Vendedor
 */
class VendedorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'nombre_completo' => $this->nombre_completo,
            'dni' => $this->dni,
            'telefono' => $this->telefono,
            'telefono_alternativo' => $this->telefono_alternativo,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'estado_provincia' => $this->estado_provincia,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'biografia' => $this->biografia,
            'foto_url' => $this->urlLogo(),
            'verificado' => (bool) $this->verificado,
            'propiedades_count' => $this->whenCounted('propiedades'),
            'usuario' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
