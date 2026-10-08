<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contacto;
use Illuminate\Http\Request;

/**
 * Formulario público de contacto.
 */
final class ContactoService
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
    ) {
    }

    /**
     * Registra el mensaje y avisa al equipo de soporte.
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrar(array $datos, ?Request $request = null): Contacto
    {
        /** @var Contacto $contacto */
        $contacto = Contacto::create($datos + [
            'ip' => $request?->ip(),
            'user_agent' => str($request?->userAgent() ?? '')->limit(250)->value(),
        ]);

        $this->notificaciones->contactoRecibido($contacto);

        return $contacto;
    }
}
