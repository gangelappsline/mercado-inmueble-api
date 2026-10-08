<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\UsaRemitente;
use App\Models\User;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Envía el enlace para restablecer la contraseña.
 */
class RecuperarPasswordMail extends BaseMailable
{
    use UsaRemitente;

    public function __construct(
        public readonly User $usuario,
        public readonly string $token,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return $this->sobre('Restablece tu contraseña');
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $minutos = (int) config('auth.passwords.users.expire', 60);

        return [
            'titulo' => 'Restablecer contraseña',
            'saludo' => sprintf('Hola %s,', $this->usuario->name),
            'intro' => 'Recibimos una solicitud para restablecer la contraseña de tu cuenta.',
            'usuario' => $this->usuario,
            'token' => $this->token,
            'minutos' => $minutos,
            'url' => rtrim((string) config('app.url'), '/').'/restablecer-password?token='.$this->token.'&email='.urlencode($this->usuario->email),
        ];
    }

    protected function vistaHtml(): string
    {
        return 'emails.recuperar-password';
    }
}
