<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Inmobiliaria;
use App\Models\User;

/**
 * Autorización del perfil de inmobiliaria: la ficha pública es visible para
 * cualquiera y sólo el propio usuario edita sus datos y su logotipo.
 */
class InmobiliariaPolicy
{
    /**
     * Ver la ficha pública de la inmobiliaria (catálogo de anunciantes).
     */
    public function view(?User $user, Inmobiliaria $inmobiliaria): bool
    {
        return true;
    }

    /**
     * Editar los datos de la inmobiliaria propia.
     */
    public function update(User $user, Inmobiliaria $inmobiliaria): bool
    {
        return $this->owns($user, $inmobiliaria);
    }

    /**
     * Cambiar el logotipo de la inmobiliaria propia.
     */
    public function actualizarLogo(User $user, Inmobiliaria $inmobiliaria): bool
    {
        return $this->owns($user, $inmobiliaria);
    }

    /**
     * Eliminar el logotipo actual.
     */
    public function eliminarLogo(User $user, Inmobiliaria $inmobiliaria): bool
    {
        return $this->owns($user, $inmobiliaria);
    }

    /**
     * Ver las métricas agregadas de la inmobiliaria propia.
     */
    public function verEstadisticas(User $user, Inmobiliaria $inmobiliaria): bool
    {
        return $this->owns($user, $inmobiliaria);
    }

    /**
     * Verificar una inmobiliaria: reservado al panel administrativo.
     */
    public function verificar(User $user, Inmobiliaria $inmobiliaria): bool
    {
        return false;
    }

    /**
     * ¿La inmobiliaria pertenece al usuario autenticado?
     */
    private function owns(User $user, Inmobiliaria $inmobiliaria): bool
    {
        return $user->esInmobiliaria() && (int) $inmobiliaria->user_id === (int) $user->getKey();
    }
}
