<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Amenidad del catálogo (usada en filtros y en el detalle de propiedades).
 *
 * @mixin \App\Models\Amenidad
 */
class AmenidadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'icono' => $this->icono,
            'categoria' => [
                'value' => $this->categoria->value,
                'label' => $this->categoria->label(),
            ],
            'activa' => (bool) $this->activa,
            'orden' => $this->orden,
        ];
    }
}
