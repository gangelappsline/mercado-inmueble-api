<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Autorización de la gestión de cuentas: exclusiva del rol `administrador`.
 *
 * Reglas de seguridad del panel:
 *  - Ninguna acción administrativa se aplica sobre la propia cuenta (evita que
 *    un agente se desactive, se degrade o se elimine a sí mismo).
 *  - Las reglas de negocio finas (mínimo de administradores activos, perfil
 *    requerido por el rol destino, revocación de tokens) viven en
 *    `App\Services\AdministracionService`.
 */
class UsuarioPolicy
{
    /**
     * Listado global de cuentas con filtros por rol, estado y búsqueda.
     */
    public function viewAny(User $user): bool
    {
        return $user->esAdministrador();
    }

    /**
     * Detalle de cualquier cuenta de la plataforma.
     */
    public function view(User $user, User $objetivo): bool
    {
        return $user->esAdministrador();
    }

    /**
     * Crear cuentas (incluidas otras cuentas de administrador).
     */
    public function create(User $user): bool
    {
        return $user->esAdministrador();
    }

    /**
     * Editar los datos de otra cuenta.
     */
    public function update(User $user, User $objetivo): bool
    {
        return $this->gestionar($user, $objetivo);
    }

    /**
     * Activar una cuenta suspendida o dada de baja.
     */
    public function activar(User $user, User $objetivo): bool
    {
        return $this->gestionar($user, $objetivo);
    }

    /**
     * Suspender una cuenta (revoca todas sus sesiones).
     */
    public function desactivar(User $user, User $objetivo): bool
    {
        return $this->gestionar($user, $objetivo);
    }

    /**
     * Reasignar el rol de una cuenta.
     */
    public function cambiarRol(User $user, User $objetivo): bool
    {
        return $this->gestionar($user, $objetivo);
    }

    /**
     * Baja lógica de la cuenta.
     */
    public function delete(User $user, User $objetivo): bool
    {
        return $this->gestionar($user, $objetivo);
    }

    /**
     * Restaurar una cuenta dada de baja.
     */
    public function restore(User $user, User $objetivo): bool
    {
        return $this->gestionar($user, $objetivo);
    }

    /**
     * ¿El administrador puede intervenir esta cuenta? Nunca la propia.
     */
    private function gestionar(User $user, User $objetivo): bool
    {
        return $user->esAdministrador() && ! $user->is($objetivo);
    }
}
