<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\UsaRemitente;
use App\Models\Propiedad;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirma al anunciante que su propiedad quedó publicada.
 */
class PropiedadPublicadaMail extends BaseMailable
{
    use UsaRemitente;

    public function __construct(public readonly Propiedad $propiedad)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return $this->sobre(sprintf('Tu propiedad %s está publicada', $this->propiedad->codigo));
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [
            'titulo' => 'Propiedad publicada',
            'saludo' => sprintf('Hola %s,', $this->propiedad->propietario?->nombreComercial() ?? 'equipo'),
            'intro' => 'Tu publicación ya está visible para todos los usuarios de la plataforma.',
            'propiedad' => $this->propiedad,
            'url' => rtrim((string) config('app.url'), '/').'/api/v1/propiedades/'.$this->propiedad->getKey(),
        ];
    }

    protected function vistaHtml(): string
    {
        return 'emails.propiedad-publicada';
    }
}
