<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\UsaRemitente;
use App\Models\Cita;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Avisa al cliente (o al anunciante) que la cita cambió de fecha u hora.
 */
class CitaReprogramadaMail extends BaseMailable
{
    use UsaRemitente;

    public function __construct(
        public readonly Cita $cita,
        public readonly ?string $fechaAnterior = null,
        public readonly ?string $horaAnterior = null,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return $this->sobre('Tu cita fue reprogramada');
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [
            'titulo' => 'Cita reprogramada',
            'saludo' => 'Hola,',
            'intro' => 'La cita cambió de fecha. Estos son los nuevos datos:',
            'cita' => $this->cita,
            'fechaAnterior' => $this->fechaAnterior,
            'horaAnterior' => $this->horaAnterior,
            'propiedad' => $this->cita->propiedad,
        ];
    }

    protected function vistaHtml(): string
    {
        return 'emails.cita-reprogramada';
    }
}
