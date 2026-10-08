<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Base de todos los correos de la plataforma: siempre en cola
 * (`ShouldQueue`), con versión HTML y versión de texto plano.
 */
abstract class BaseMailable extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Cantidad de intentos antes de descartar el envío.
     */
    public int $tries = 3;

    /**
     * Cola dedicada a correos transaccionales.
     */
    public function __construct()
    {
        $this->onQueue('emails');
    }

    /**
     * Datos disponibles en las vistas Blade.
     *
     * @return array<string, mixed>
     */
    abstract public function datos(): array;

    /**
     * Encabezado del correo (asunto y destinatario ya resueltos en cada hijo).
     */
    abstract public function envelope(): Envelope;

    /**
     * Contenido HTML + texto plano.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->vistaHtml(),
            text: $this->vistaTexto(),
            with: $this->datos(),
        );
    }

    /**
     * Nombre de la vista HTML (resources/views/emails/...).
     */
    abstract protected function vistaHtml(): string;

    /**
     * Nombre de la vista de texto plano.
     */
    protected function vistaTexto(): string
    {
        return $this->vistaHtml().'-text';
    }
}
