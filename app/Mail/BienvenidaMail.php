<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\UsaRemitente;
use App\Models\User;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo de bienvenida; el contenido cambia según el rol del usuario.
 */
class BienvenidaMail extends BaseMailable
{
    use UsaRemitente;

    public function __construct(public readonly User $usuario)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return $this->sobre(sprintf('Bienvenido a %s', config('app.name')));
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return [
            'titulo' => sprintf('¡Bienvenido, %s!', $this->usuario->nombreVisible()),
            'saludo' => sprintf('Hola %s,', $this->usuario->name),
            'intro' => $this->mensajeSegunRol(),
            'usuario' => $this->usuario,
            'rol' => $this->usuario->role,
            'url' => rtrim((string) config('app.url'), '/'),
        ];
    }

    /**
     * Texto de bienvenida adaptado a cada rol.
     */
    private function mensajeSegunRol(): string
    {
        return match ($this->usuario->role) {
            \App\Enums\Role::Inmobiliaria => 'Tu cuenta de inmobiliaria está lista: publica propiedades, gestiona tu agenda y responde a los interesados desde el panel.',
            \App\Enums\Role::Vendedor => 'Tu cuenta de vendedor está lista: publica tus propiedades y recibe consultas de clientes interesados.',
            \App\Enums\Role::Cliente => 'Tu cuenta está lista: guarda tus propiedades favoritas y solicita visitas cuando quieras.',
            \App\Enums\Role::Administrador => 'Tu cuenta de administrador está lista: gestiona inmobiliarias, vendedores, clientes y la moderación de publicaciones desde el panel de administración.',
        };
    }

    protected function vistaHtml(): string
    {
        return 'emails.bienvenida';
    }
}
