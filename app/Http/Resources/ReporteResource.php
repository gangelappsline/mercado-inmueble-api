<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Métrica diaria agregada por propiedad (vistas, contactos, favoritos, citas
 * y conversiones).
 *
 * @mixin \App\Models\Reporte
 */
class ReporteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => [
                'value' => $this->tipo->value,
                'label' => $this->tipo->label(),
            ],
            'descripcion' => $this->descripcion(),
            'fecha' => $this->fecha?->toDateString(),
            'cantidad' => (int) $this->cantidad,
            'valor' => $this->valor !== null ? (float) $this->valor : null,
            'metadata' => $this->metadata ?? [],
            'propiedad_id' => $this->propiedad_id,
            'propiedad' => $this->when(
                $this->relationLoaded('propiedad') && $this->propiedad !== null,
                fn (): array => [
                    'id' => $this->propiedad->id,
                    'codigo' => $this->propiedad->codigo,
                    'titulo' => $this->propiedad->titulo,
                ],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
