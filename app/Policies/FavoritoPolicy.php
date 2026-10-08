<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Favorito;
use App\Models\User;

/**
 * Autorización de favoritos: cada cliente administra únicamente los suyos.
 */
class FavoritoPolicy
{
    /**
     * Listar los favoritos propios.
     */
    public function viewAny(User $user): bool
    {
        return $user->esCliente();
    }

    /**
     * Ver un favorito propio.
     */
    public function view(User $user, Favorito $favorito): bool
    {
        return $this->owns($user, $favorito);
    }

    /**
     * Guardar una propiedad en favoritos.
     */
    public function create(User $user): bool
    {
        return $user->esCliente() && $user->perfilCliente() !== null;
    }

    /**
     * Actualizar la nota del favorito.
     */
    public function update(User $user, Favorito $favorito): bool
    {
        return $this->owns($user, $favorito);
    }

    /**
     * Quitar la propiedad de favoritos.
     */
    public function delete(User $user, Favorito $favorito): bool
    {
        return $this->owns($user, $favorito);
    }

    /**
     * ¿El favorito pertenece al cliente autenticado?
     */
    private function owns(User $user, Favorito $favorito): bool
    {
        return $user->esCliente()
            && $favorito->cliente !== null
            && (int) $favorito->cliente->user_id === (int) $user->getKey();
    }
}
