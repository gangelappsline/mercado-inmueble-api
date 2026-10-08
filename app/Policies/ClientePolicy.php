<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

/**
 * Autorización del perfil de cliente: cada cliente ve y edita únicamente su
 * propio perfil, preferencias y presupuesto.
 */
class ClientePolicy
{
    /**
     * Ver el perfil propio (los perfiles de clientes no son públicos).
     */
    public function view(User $user, Cliente $cliente): bool
    {
        return $this->owns($user, $cliente);
    }

    /**
     * Editar el perfil propio: datos de contacto y preferencias.
     */
    public function update(User $user, Cliente $cliente): bool
    {
        return $this->owns($user, $cliente);
    }

    /**
     * Eliminar la propia cuenta de cliente (baja lógica).
     */
    public function delete(User $user, Cliente $cliente): bool
    {
        return $this->owns($user, $cliente);
    }

    /**
     * ¿El cliente pertenece al usuario autenticado?
     */
    private function owns(User $user, Cliente $cliente): bool
    {
        return $user->esCliente() && (int) $cliente->user_id === (int) $user->getKey();
    }
}
