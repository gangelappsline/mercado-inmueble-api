<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Encabezado estándar de los correos de la plataforma (remitente corporativo
 * y copia oculta a soporte cuando aplica).
 */
trait UsaRemitente
{
    /**
     * Construye el `Envelope` con el remitente no-reply configurado.
     */
    protected function sobre(string $asunto, ?string $responderA = null): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('mail.from.address', 'no-reply@mercadoinmueble.com'),
                (string) config('mail.from.name', config('app.name')),
            ),
            replyTo: $responderA !== null ? [new Address($responderA)] : [],
            subject: $asunto,
            tags: ['mercado-inmueble'],
        );
    }
}
