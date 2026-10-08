<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\UsaRemitente;
use App\Models\Cita;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notifica la cancelación de una cita.
 */
class CitaCanceladaMail extends BaseMailable
{
    use UsaRemitente;

    public function __construct(
        public readonly Cita $cita,
        public readonly string $canceladaPor = 'sistema',
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return $this->sobre('Tu cita fue cancelada');
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [
            'titulo' => 'Cita cancelada',
            'saludo' => 'Hola,',
            'intro' => 'La siguiente cita fue cancelada:',
            'cita' => $this->cita,
            'canceladaPor' => $this->canceladaPor,
            'propiedad' => $this->cita->propiedad,
        ];
    }

    protected function vistaHtml(): string
    {
        return 'emails.cita-cancelada';
    }
}
