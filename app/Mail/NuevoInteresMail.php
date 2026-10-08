<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\UsaRemitente;
use App\Models\Interes;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Notifica a la inmobiliaria/vendedor que un cliente mostró interés.
 */
class NuevoInteresMail extends BaseMailable
{
    use UsaRemitente;

    public function __construct(public readonly Interes $interes)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return $this->sobre(sprintf(
            'Nuevo interesado en %s',
            $this->interes->propiedad?->codigo ?? 'tu propiedad',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $propiedad = $this->interes->propiedad;
        $cliente = $this->interes->cliente;

        return [
            'titulo' => 'Nuevo interesado',
            'saludo' => sprintf('Hola %s,', $this->interes->propiedad?->propietario?->nombreComercial() ?? 'equipo'),
            'intro' => 'Un cliente registró interés en una de tus publicaciones.',
            'propiedad' => $propiedad,
            'cliente' => $cliente,
            'mensaje' => $this->interes->mensaje,
            'origen' => $this->interes->origen,
            'url' => rtrim((string) config('app.url'), '/').'/api/v1/inmobiliaria/interesados/'.$this->interes->getKey(),
        ];
    }

    protected function vistaHtml(): string
    {
        return 'emails.nuevo-interes';
    }
}
