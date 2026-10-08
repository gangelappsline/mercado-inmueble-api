<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vendedor;

/**
 * Autorización del perfil de vendedor: la ficha pública es visible; sólo el
 * propio vendedor edita sus datos y su fotografía.
 */
class VendedorPolicy
{
    /**
     * Ver la ficha pública del vendedor.
     */
    public function view(?User $user, Vendedor $vendedor): bool
    {
        return true;
    }

    /**
     * Editar el perfil propio del vendedor.
     */
    public function update(User $user, Vendedor $vendedor): bool
    {
        return $this->owns($user, $vendedor);
    }

    /**
     * Cambiar la fotografía del vendedor.
     */
    public function actualizarFoto(User $user, Vendedor $vendedor): bool
    {
        return $this->owns($user, $vendedor);
    }

    /**
     * Ver las métricas del vendedor propio.
     */
    public function verEstadisticas(User $user, Vendedor $vendedor): bool
    {
        return $this->owns($user, $vendedor);
    }

    /**
     * ¿El vendedor pertenece al usuario autenticado?
     */
    private function owns(User $user, Vendedor $vendedor): bool
    {
        return $user->esVendedor() && (int) $vendedor->user_id === (int) $user->getKey();
    }
}
