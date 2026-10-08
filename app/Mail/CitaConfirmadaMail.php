<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\UsaRemitente;
use App\Models\Cita;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirma al cliente la cita agendada.
 */
class CitaConfirmadaMail extends BaseMailable
{
    use UsaRemitente;

    public function __construct(public readonly Cita $cita)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return $this->sobre('Tu cita fue confirmada');
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [
            'titulo' => 'Cita confirmada',
            'saludo' => sprintf('Hola %s,', $this->cita->cliente?->nombreCompleto() ?: 'cliente'),
            'intro' => 'Tu cita quedó confirmada. Te esperamos en la fecha indicada.',
            'cita' => $this->cita,
            'propiedad' => $this->cita->propiedad,
            'propietario' => $this->cita->propietario,
        ];
    }

    protected function vistaHtml(): string
    {
        return 'emails.cita-confirmada';
    }
}
