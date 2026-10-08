<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Mensaje;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Avisa al cliente que el anunciante respondió su consulta.
 */
final class InteresRespondidoNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Mensaje $mensaje)
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
            'tipo' => 'interes_respondido',
            'titulo' => 'Nueva respuesta',
            'mensaje' => $this->mensaje->extracto(140),
            'hilo_id' => $this->mensaje->hilo_id,
            'propiedad_id' => $this->mensaje->hilo?->interes?->propiedad_id,
            'url' => '/api/v1/cliente/intereses/'.$this->mensaje->hilo?->interes_id.'/mensajes',
        ];
    }
}
