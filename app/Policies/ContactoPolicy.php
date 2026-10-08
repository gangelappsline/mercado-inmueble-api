<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contacto;
use App\Models\User;

/**
 * Autorización de los mensajes del formulario público de contacto: la bandeja
 * de soporte es exclusiva del panel de administración.
 */
class ContactoPolicy
{
    /**
     * Bandeja de mensajes de contacto (pendientes y atendidos).
     */
    public function viewAny(User $user): bool
    {
        return $user->esAdministrador();
    }

    /**
     * Detalle del mensaje con su trazabilidad (IP y usuario agente).
     */
    public function view(User $user, Contacto $contacto): bool
    {
        return $user->esAdministrador();
    }

    /**
     * Marcar el mensaje como atendido.
     */
    public function atender(User $user, Contacto $contacto): bool
    {
        return $user->esAdministrador();
    }

    /**
     * Eliminar el mensaje de la bandeja.
     */
    public function delete(User $user, Contacto $contacto): bool
    {
        return $user->esAdministrador();
    }
}
