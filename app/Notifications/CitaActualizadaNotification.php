<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Avisa al cliente sobre un cambio de estado de su cita (confirmada,
 * reprogramada o cancelada).
 */
final class CitaActualizadaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Cita $cita,
        public readonly string $accion,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'cita_'.$this->accion,
            'titulo' => sprintf('Cita %s', $this->accion),
            'mensaje' => $this->cita->resumen(),
            'cita_id' => $this->cita->getKey(),
            'estado' => $this->cita->estado->value,
            'fecha' => $this->cita->fecha?->toDateString(),
            'hora' => substr((string) $this->cita->hora, 0, 5),
            'propiedad_id' => $this->cita->propiedad_id,
            'url' => '/api/v1/cliente/citas',
        ];
    }
}
