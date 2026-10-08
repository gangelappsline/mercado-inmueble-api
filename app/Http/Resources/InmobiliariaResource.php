<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha de inmobiliaria (perfil propio y directorio público).
 *
 * @mixin \App\Models\Inmobiliaria
 */
class InmobiliariaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'razon_social' => $this->razon_social,
            'nombre_comercial' => $this->nombre_comercial,
            'nombre_publico' => $this->nombreComercial(),
            'ruc' => $this->ruc,
            'logo_url' => $this->urlLogo(),
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'telefono_alternativo' => $this->telefono_alternativo,
            'web' => $this->web,
            'descripcion' => $this->descripcion,
            'ciudad' => $this->ciudad,
            'estado_provincia' => $this->estado_provincia,
            'pais' => $this->pais,
            'verificado' => (bool) $this->verificado,
            'propiedades_count' => $this->whenCounted('propiedades'),
            'usuario' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
