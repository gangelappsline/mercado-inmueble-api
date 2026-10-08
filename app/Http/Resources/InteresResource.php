<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\EstadoInteres;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Interés (lead) de un cliente por una propiedad.
 *
 * @mixin \App\Models\Interes
 */
class InteresResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => [
                'value' => $this->estado->value,
                'label' => $this->estado->label(),
                'es_final' => $this->esFinal(),
            ],
            'mensaje' => $this->mensaje,
            'origen' => $this->origen,
            'contactado' => (bool) $this->contactado,
            'es_nuevo' => $this->estado === EstadoInteres::Nuevo,
            'antiguedad_minutos' => $this->antiguedadMinutos(),
            'atendido_en' => $this->atendido_en?->toIso8601String(),
            'cerrado_en' => $this->cerrado_en?->toIso8601String(),
            'propiedad' => new PropiedadResource($this->whenLoaded('propiedad')),
            'cliente' => new ClienteResource($this->whenLoaded('cliente')),
            'hilo_id' => $this->whenLoaded('hilo', fn (): ?int => $this->hilo?->getKey()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
