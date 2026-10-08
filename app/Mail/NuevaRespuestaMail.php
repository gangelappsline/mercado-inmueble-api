<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\UsaRemitente;
use App\Models\Mensaje;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notifica al cliente que el anunciante respondió su consulta.
 */
class NuevaRespuestaMail extends BaseMailable
{
    use UsaRemitente;

    public function __construct(public readonly Mensaje $mensaje)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return $this->sobre(sprintf('Respondieron tu consulta: %s', $this->mensaje->hilo?->asunto ?? 'tu propiedad de interés'));
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [
            'titulo' => 'Tienes una nueva respuesta',
            'saludo' => 'Hola,',
            'intro' => $this->mensaje->esDelCliente()
                ? 'El cliente escribió de nuevo en la conversación.'
                : 'El anunciante respondió a tu consulta.',
            'mensaje' => $this->mensaje,
            'hilo' => $this->mensaje->hilo,
            'propiedad' => $this->mensaje->hilo?->interes?->propiedad,
            'url' => rtrim((string) config('app.url'), '/').'/api/v1/cliente/intereses/'.$this->mensaje->hilo?->interes_id.'/mensajes',
        ];
    }

    protected function vistaHtml(): string
    {
        return 'emails.nueva-respuesta';
    }
}
