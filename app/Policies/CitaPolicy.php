<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cita;
use App\Models\User;

/**
 * Autorización de citas: las gestionan el anunciante propietario y el cliente
 * que las solicitó; el resto de usuarios no tiene acceso.
 */
class CitaPolicy
{
    /**
     * Agenda del panel o listado de citas propias del cliente.
     */
    public function viewAny(User $user): bool
    {
        return $user->publicaPropiedades() || $user->esCliente();
    }

    /**
     * Ver el detalle de la cita.
     */
    public function view(User $user, Cita $cita): bool
    {
        return $cita->participa($user);
    }

    /**
     * Solicitar una cita: sólo clientes sobre propiedades publicadas.
     */
    public function create(User $user): bool
    {
        return $user->esCliente() && $user->perfilCliente() !== null;
    }

    /**
     * Confirmar la solicitud: sólo el anunciante.
     */
    public function confirmar(User $user, Cita $cita): bool
    {
        return $cita->esDelPropietario($user) && $cita->puedeConfirmarse();
    }

    /**
     * Reprogramar: cualquier participante, mientras la cita no sea final.
     */
    public function reprogramar(User $user, Cita $cita): bool
    {
        return $cita->participa($user) && $cita->puedeReprogramarse();
    }

    /**
     * Cancelar: cualquier participante, mientras la cita no sea final.
     */
    public function cancelar(User $user, Cita $cita): bool
    {
        return $cita->participa($user) && $cita->puedeCancelarse();
    }

    /**
     * Marcar la visita como realizada: sólo el anunciante.
     */
    public function completar(User $user, Cita $cita): bool
    {
        return $cita->esDelPropietario($user) && ! $cita->estado->esFinal();
    }

    /**
     * Marcar inasistencia: sólo el anunciante.
     */
    public function marcarNoAsistio(User $user, Cita $cita): bool
    {
        return $cita->esDelPropietario($user) && ! $cita->estado->esFinal();
    }
}
