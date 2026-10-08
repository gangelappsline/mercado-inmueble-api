<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Hilo;
use App\Models\User;

/**
 * Autorización de los hilos de mensajes: sólo las dos partes de la
 * conversación (anunciante y cliente) pueden leer o escribir.
 */
class HiloPolicy
{
    /**
     * Bandeja de mensajes (inmobiliaria, vendedor o cliente).
     */
    public function viewAny(User $user): bool
    {
        return $user->publicaPropiedades() || $user->esCliente();
    }

    /**
     * Leer el hilo y sus mensajes.
     */
    public function view(User $user, Hilo $hilo): bool
    {
        return $hilo->participa($user);
    }

    /**
     * Enviar un mensaje: el hilo debe seguir abierto.
     */
    public function responder(User $user, Hilo $hilo): bool
    {
        return $hilo->participa($user) && ! $hilo->cerrado;
    }

    /**
     * Cerrar la conversación (sólo el anunciante).
     */
    public function cerrar(User $user, Hilo $hilo): bool
    {
        return $hilo->esPropietario($user) && ! $hilo->cerrado;
    }

    /**
     * Reabrir la conversación (sólo el anunciante).
     */
    public function reabrir(User $user, Hilo $hilo): bool
    {
        return $hilo->esPropietario($user) && $hilo->cerrado;
    }
}
