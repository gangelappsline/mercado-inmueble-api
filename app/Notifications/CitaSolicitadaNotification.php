<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Avisa al anunciante que un cliente solicitó una cita.
 */
final class CitaSolicitadaNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Cita $cita)
    {
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
            'tipo' => 'cita_solicitada',
            'titulo' => 'Nueva solicitud de cita',
            'mensaje' => sprintf(
                '%s solicitó una cita para el %s a las %s',
                $this->cita->cliente?->nombreCompleto() ?: 'Un cliente',
                $this->cita->inicio()->translatedFormat('d \d\e F'),
                $this->cita->inicio()->format('H:i'),
            ),
            'cita_id' => $this->cita->getKey(),
            'propiedad_id' => $this->cita->propiedad_id,
            'url' => '/api/v1/inmobiliaria/citas/'.$this->cita->getKey(),
        ];
    }
}
