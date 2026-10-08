<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Registro de la bitácora de auditoría (`activity_log`) para el panel de
 * administración: qué se hizo, sobre qué entidad y quién lo hizo.
 *
 * @mixin \Spatie\Activitylog\Models\Activity
 */
class ActividadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log' => $this->log_name,
            'evento' => $this->event,
            'descripcion' => $this->description,
            'sujeto' => [
                'tipo' => $this->subject_type,
                'id' => $this->subject_id,
            ],
            'agente' => $this->agente(),
            'propiedades' => $this->propiedades(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Usuario que ejecutó la acción (null cuando fue el sistema).
     *
     * @return array<string, mixed>|null
     */
    private function agente(): ?array
    {
        $causer = $this->whenLoaded('causer');

        if (! $causer instanceof User) {
            return null;
        }

        return [
            'id' => $causer->getKey(),
            'name' => $causer->name,
            'email' => $causer->email,
            'role' => $causer->role?->value,
        ];
    }

    /**
     * Propiedades registradas por el auditor (valores antiguos/nuevos, motivos…).
     *
     * @return array<string, mixed>
     */
    private function propiedades(): array
    {
        $propiedades = $this->properties;

        if ($propiedades instanceof Collection) {
            return $propiedades->all();
        }

        return is_array($propiedades) ? $propiedades : [];
    }
}
