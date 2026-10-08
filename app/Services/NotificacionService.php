<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\BienvenidaMail;
use App\Mail\BaseMailable;
use App\Mail\CitaCanceladaMail;
use App\Mail\CitaConfirmadaMail;
use App\Mail\CitaReprogramadaMail;
use App\Mail\NuevaCitaMail;
use App\Mail\NuevaRespuestaMail;
use App\Mail\NuevoInteresMail;
use App\Mail\PropiedadPublicadaMail;
use App\Mail\RecuperarPasswordMail;
use App\Models\Cita;
use App\Models\Contacto;
use App\Models\Interes;
use App\Models\Mensaje;
use App\Models\Propiedad;
use App\Models\User;
use App\Notifications\CitaActualizadaNotification;
use App\Notifications\CitaSolicitadaNotification;
use App\Notifications\InteresRegistradoNotification;
use App\Notifications\InteresRespondidoNotification;
use App\Notifications\PropiedadPublicadaNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Centraliza TODAS las notificaciones salientes: correos transaccionales
 * (encolados) y notificaciones en base de datos.
 *
 * Ninguna notificación puede romper la petición HTTP: los fallos se registran
 * en el log y la operación de negocio continúa.
 */
final class NotificacionService
{
    /**
     * Un cliente registró interés: avisa al anunciante.
     */
    public function nuevoInteres(Interes $interes): void
    {
        $destinatario = $interes->propiedad?->propietario?->user;

        if ($destinatario === null) {
            return;
        }

        $this->enviarCorreo($destinatario->email, new NuevoInteresMail($interes));
        $this->notificar($destinatario, new InteresRegistradoNotification($interes));
    }

    /**
     * El anunciante respondió: avisa al cliente.
     */
    public function respuestaDelPropietario(Mensaje $mensaje): void
    {
        $destinatario = $mensaje->hilo?->clienteUser();

        if ($destinatario === null) {
            return;
        }

        $this->enviarCorreo($destinatario->email, new NuevaRespuestaMail($mensaje));
        $this->notificar($destinatario, new InteresRespondidoNotification($mensaje));
    }

    /**
     * Un cliente solicitó una cita: avisa al anunciante.
     */
    public function nuevaCita(Cita $cita): void
    {
        $destinatario = $cita->propietario?->user;

        if ($destinatario === null) {
            return;
        }

        $this->enviarCorreo($destinatario->email, new NuevaCitaMail($cita));
        $this->notificar($destinatario, new CitaSolicitadaNotification($cita));
    }

    /**
     * La cita fue confirmada: avisa al cliente.
     */
    public function citaConfirmada(Cita $cita): void
    {
        $destinatario = $cita->cliente?->user;

        if ($destinatario === null) {
            return;
        }

        $this->enviarCorreo($destinatario->email, new CitaConfirmadaMail($cita));
        $this->notificar($destinatario, new CitaActualizadaNotification($cita, 'confirmada'));
    }

    /**
     * La cita cambió de fecha: avisa a ambos participantes.
     */
    public function citaReprogramada(Cita $cita, ?string $fechaAnterior = null, ?string $horaAnterior = null): void
    {
        $correoCliente = $cita->cliente?->user;
        $correoPropietario = $cita->propietario?->user;

        if ($correoCliente !== null) {
            $this->enviarCorreo($correoCliente->email, new CitaReprogramadaMail($cita, $fechaAnterior, $horaAnterior));
            $this->notificar($correoCliente, new CitaActualizadaNotification($cita, 'reprogramada'));
        }

        if ($correoPropietario !== null) {
            $this->enviarCorreo($correoPropietario->email, new CitaReprogramadaMail($cita, $fechaAnterior, $horaAnterior));
        }
    }

    /**
     * La cita fue cancelada: avisa al cliente y al anunciante.
     */
    public function citaCancelada(Cita $cita, string $canceladaPor = 'sistema'): void
    {
        $cliente = $cita->cliente?->user;
        $propietario = $cita->propietario?->user;

        if ($cliente !== null) {
            $this->enviarCorreo($cliente->email, new CitaCanceladaMail($cita, $canceladaPor));
            $this->notificar($cliente, new CitaActualizadaNotification($cita, 'cancelada'));
        }

        if ($propietario !== null && $canceladaPor !== 'anunciante') {
            $this->enviarCorreo($propietario->email, new CitaCanceladaMail($cita, $canceladaPor));
        }
    }

    /**
     * Correo de bienvenida según el rol del usuario registrado.
     */
    public function bienvenida(User $usuario): void
    {
        $this->enviarCorreo($usuario->email, new BienvenidaMail($usuario));
    }

    /**
     * Confirmación de publicación al anunciante.
     */
    public function propiedadPublicada(Propiedad $propiedad): void
    {
        $destinatario = $propiedad->propietario?->user;

        if ($destinatario === null) {
            return;
        }

        $this->enviarCorreo($destinatario->email, new PropiedadPublicadaMail($propiedad));
        $this->notificar($destinatario, new PropiedadPublicadaNotification($propiedad));
    }

    /**
     * Enlace de restablecimiento de contraseña.
     */
    public function recuperarPassword(User $usuario, string $token): void
    {
        $this->enviarCorreo($usuario->email, new RecuperarPasswordMail($usuario, $token));
    }

    /**
     * Formulario público de contacto: se avisa al equipo de soporte.
     */
    public function contactoRecibido(Contacto $contacto): void
    {
        $admin = (string) config('mercado.notificaciones.email_admin', config('mail.from.address'));

        $this->enviarCorreoCrudo(
            $admin,
            sprintf('[Contacto] %s', $contacto->asunto),
            sprintf(
                "Nombre: %s\nCorreo: %s\nTeléfono: %s\nIP: %s\n\n%s",
                $contacto->nombre,
                $contacto->email,
                $contacto->telefono ?? '—',
                $contacto->ip ?? '—',
                $contacto->mensaje,
            ),
        );
    }

    /**
     * Envía un `Mailable` respetando la configuración de colas.
     */
    public function enviarCorreo(string $destinatario, BaseMailable $mailable): void
    {
        try {
            $pendiente = Mail::to($destinatario);

            if ((bool) config('mercado.notificaciones.encolar', true)) {
                $pendiente->queue($mailable);
            } else {
                $pendiente->send($mailable);
            }
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el correo', [
                'destinatario' => $destinatario,
                'mailable' => $mailable::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envía un correo de texto plano sin plantilla (avisos internos).
     */
    private function enviarCorreoCrudo(string $destinatario, string $asunto, string $cuerpo): void
    {
        try {
            Mail::raw($cuerpo, static function ($mensaje) use ($destinatario, $asunto): void {
                $mensaje->to($destinatario)->subject($asunto);
            });
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el correo interno', [
                'destinatario' => $destinatario,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Guarda la notificación en base de datos (canal `database`).
     */
    private function notificar(User $destinatario, object $notificacion): void
    {
        if (! (bool) config('mercado.notificaciones.canal_database', true)) {
            return;
        }

        try {
            $destinatario->notify($notificacion);
        } catch (Throwable $e) {
            Log::error('No se pudo registrar la notificación', [
                'usuario' => $destinatario->getKey(),
                'notificacion' => $notificacion::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
