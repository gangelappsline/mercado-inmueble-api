<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cita de la agenda (visita, virtual o llamada) con su estado y ventana horaria.
 *
 * @mixin \App\Models\Cita
 */
class CitaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha?->toDateString(),
            'hora' => $this->hora,
            'duracion_minutos' => $this->duracion_minutos,
            'rango_horario' => $this->rangoHorario(),
            'tipo' => [
                'value' => $this->tipo->value,
                'label' => $this->tipo->label(),
            ],
            'estado' => [
                'value' => $this->estado->value,
                'label' => $this->estado->label(),
                'es_final' => $this->estado->esFinal(),
            ],
            'lugar' => $this->lugar,
            'enlace_virtual' => $this->enlace_virtual,
            'notas' => $this->notas,
            'motivo_cancelacion' => $this->motivo_cancelacion,
            'resumen' => $this->resumen(),
            'puede_confirmarse' => $this->puedeConfirmarse(),
            'puede_reprogramarse' => $this->puedeReprogramarse(),
            'puede_cancelarse' => $this->puedeCancelarse(),
            'propiedad' => new PropiedadResource($this->whenLoaded('propiedad')),
            'cliente' => new ClienteResource($this->whenLoaded('cliente')),
            'interes_id' => $this->interes_id,
            'confirmada_en' => $this->confirmada_en?->toIso8601String(),
            'cancelada_en' => $this->cancelada_en?->toIso8601String(),
            'completada_en' => $this->completada_en?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
