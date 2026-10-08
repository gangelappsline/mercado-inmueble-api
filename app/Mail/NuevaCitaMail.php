<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\UsaRemitente;
use App\Models\Cita;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notifica a la inmobiliaria/vendedor que un cliente solicitó una cita.
 */
class NuevaCitaMail extends BaseMailable
{
    use UsaRemitente;

    public function __construct(public readonly Cita $cita)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return $this->sobre(sprintf('Nueva solicitud de cita · %s', $this->cita->propiedad?->codigo ?? 'agenda'));
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [
            'titulo' => 'Nueva solicitud de cita',
            'saludo' => sprintf('Hola %s,', $this->cita->propietario?->nombreComercial() ?? 'equipo'),
            'intro' => 'Un cliente solicitó una cita para ver una de tus propiedades.',
            'cita' => $this->cita,
            'cliente' => $this->cita->cliente,
            'propiedad' => $this->cita->propiedad,
            'url' => rtrim((string) config('app.url'), '/').'/api/v1/inmobiliaria/citas/'.$this->cita->getKey(),
        ];
    }

    protected function vistaHtml(): string
    {
        return 'emails.nueva-cita';
    }
}
