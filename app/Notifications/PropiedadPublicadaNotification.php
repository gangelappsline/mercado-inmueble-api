<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Propiedad;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Confirma al anunciante que su propiedad ya es visible en el catálogo.
 */
final class PropiedadPublicadaNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Propiedad $propiedad)
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
            'tipo' => 'propiedad_publicada',
            'titulo' => 'Propiedad publicada',
            'mensaje' => sprintf('%s (%s) ya está visible.', $this->propiedad->titulo, $this->propiedad->codigo),
            'propiedad_id' => $this->propiedad->getKey(),
            'url' => '/api/v1/inmobiliaria/propiedades/'.$this->propiedad->getKey(),
        ];
    }
}
