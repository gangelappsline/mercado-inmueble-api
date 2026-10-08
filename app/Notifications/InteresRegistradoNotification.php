<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Interes;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación en base de datos para el anunciante cuando un cliente muestra
 * interés en una de sus propiedades (el correo lo envía NotificacionService).
 */
final class InteresRegistradoNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Interes $interes)
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
     * Datos que se guardan en la tabla `notifications`.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'interes_registrado',
            'titulo' => 'Nuevo interesado',
            'mensaje' => sprintf(
                '%s está interesado en %s',
                $this->interes->cliente?->nombreCompleto() ?: 'Un cliente',
                $this->interes->propiedad?->titulo ?? 'tu propiedad',
            ),
            'interes_id' => $this->interes->getKey(),
            'propiedad_id' => $this->interes->propiedad_id,
            'propiedad_codigo' => $this->interes->propiedad?->codigo,
            'url' => '/api/v1/inmobiliaria/interesados/'.$this->interes->getKey(),
        ];
    }
}
