<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Interes;
use App\Models\User;

/**
 * Autorización de intereses: sólo el anunciante dueño de la propiedad y el
 * cliente que lo registró pueden verlo y gestionarlo.
 */
class InteresPolicy
{
    /**
     * Bandeja de interesados (panel) o listado propio del cliente.
     */
    public function viewAny(User $user): bool
    {
        return $user->publicaPropiedades() || $user->esCliente();
    }

    /**
     * Ver el detalle del interés.
     */
    public function view(User $user, Interes $interes): bool
    {
        return $interes->participa($user);
    }

    /**
     * Registrar un interés: sólo clientes, sobre propiedades publicadas.
     */
    public function create(User $user): bool
    {
        return $user->esCliente() && $user->perfilCliente() !== null;
    }

    /**
     * Responder al interesado (mensaje o cambio de estado comercial).
     */
    public function responder(User $user, Interes $interes): bool
    {
        return $user->publicaPropiedades()
            && $interes->propiedad !== null
            && $interes->propiedad->esDelUsuario($user);
    }

    /**
     * Cambiar el estado del embudo (atendido, cerrado, descartado).
     */
    public function cambiarEstado(User $user, Interes $interes): bool
    {
        return $this->responder($user, $interes) && ! $interes->esFinal();
    }

    /**
     * Retirar el interés propio (cliente).
     */
    public function delete(User $user, Interes $interes): bool
    {
        return $user->esCliente()
            && $interes->cliente !== null
            && (int) $interes->cliente->user_id === (int) $user->getKey();
    }
}
