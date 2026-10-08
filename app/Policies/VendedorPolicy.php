<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vendedor;

/**
 * Autorización del perfil de vendedor: la ficha pública es visible, sólo el
 * propio vendedor edita sus datos y su fotografía, y el panel de
 * administración (rol `administrador`) los gestiona y verifica.
 */
class VendedorPolicy
{
    /**
     * Listado administrativo de vendedores (panel de administración).
     */
    public function viewAny(User $user): bool
    {
        return $user->esAdministrador();
    }

    /**
     * Gestión administrativa de la cuenta (suspender, verificar, métricas).
     */
    public function gestionar(User $user, Vendedor $vendedor): bool
    {
        return $user->esAdministrador();
    }

    /**
     * Verificar (o retirar la verificación de) un vendedor.
     */
    public function verificar(User $user, Vendedor $vendedor): bool
    {
        return $user->esAdministrador();
    }

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
     * Ver las métricas del vendedor propio (o de cualquiera desde el panel de
     * administración).
     */
    public function verEstadisticas(User $user, Vendedor $vendedor): bool
    {
        return $this->owns($user, $vendedor) || $user->esAdministrador();
    }

    /**
     * ¿El vendedor pertenece al usuario autenticado?
     */
    private function owns(User $user, Vendedor $vendedor): bool
    {
        return $user->esVendedor() && (int) $vendedor->user_id === (int) $user->getKey();
    }
}
