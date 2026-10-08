<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EstadoPropiedad;
use App\Models\Propiedad;
use App\Models\User;

/**
 * Autorización de las propiedades: cada anunciante administra únicamente sus
 * publicaciones, los clientes nunca acceden al panel y el rol `administrador`
 * modera cualquier publicación de la plataforma.
 */
class PropiedadPolicy
{
    /**
     * Listar el propio listado del panel (o el global en moderación).
     */
    public function viewAny(User $user): bool
    {
        return $user->publicaPropiedades() || $user->esAdministrador();
    }

    /**
     * Ver el detalle administrativo (incluye borradores y pausadas).
     */
    public function view(User $user, Propiedad $propiedad): bool
    {
        return $this->owns($user, $propiedad) || $user->esAdministrador();
    }

    /**
     * Moderación administrativa: destacar, pausar, publicar, rechazar o dar de
     * baja cualquier publicación, sin importar su anunciante.
     */
    public function moderar(User $user, Propiedad $propiedad): bool
    {
        return $user->esAdministrador();
    }

    /**
     * Crear publicaciones: el rol define qué perfil será el propietario.
     */
    public function create(User $user): bool
    {
        return $user->publicaPropiedades() && $user->propietario() !== null;
    }

    /**
     * Actualizar una publicación propia (no cerrada).
     */
    public function update(User $user, Propiedad $propiedad): bool
    {
        return $this->owns($user, $propiedad) && ! $propiedad->esCerrada();
    }

    /**
     * Eliminar una publicación propia (borrado lógico).
     */
    public function delete(User $user, Propiedad $propiedad): bool
    {
        return $this->owns($user, $propiedad);
    }

    /**
     * Publicar: sólo el dueño, desde borrador o pausada.
     */
    public function publicar(User $user, Propiedad $propiedad): bool
    {
        return $this->owns($user, $propiedad) && $propiedad->estado->sePuedePublicar();
    }

    /**
     * Pausar una publicación visible.
     */
    public function pausar(User $user, Propiedad $propiedad): bool
    {
        return $this->owns($user, $propiedad) && $propiedad->estado === EstadoPropiedad::Publicada;
    }

    /**
     * Marcar la operación como cerrada (vendida/alquilada).
     */
    public function cerrar(User $user, Propiedad $propiedad): bool
    {
        return $this->owns($user, $propiedad) && ! $propiedad->esCerrada();
    }

    /**
     * Restaurar una publicación eliminada lógicamente.
     */
    public function restore(User $user, Propiedad $propiedad): bool
    {
        return $user->esAdministrador()
            || ($user->esInmobiliaria() && $this->owns($user, $propiedad));
    }

    /**
     * Destacar/retirar destacado: inmobiliarias sobre sus publicaciones y el
     * panel de administración sobre cualquiera.
     */
    public function destacar(User $user, Propiedad $propiedad): bool
    {
        return $user->esAdministrador()
            || ($user->esInmobiliaria() && $this->owns($user, $propiedad));
    }

    /**
     * Consultar métricas y agregados de la publicación.
     */
    public function verEstadisticas(User $user, Propiedad $propiedad): bool
    {
        return $this->owns($user, $propiedad) || $user->esAdministrador();
    }

    /**
     * ¿La propiedad pertenece al perfil anunciante del usuario?
     */
    private function owns(User $user, Propiedad $propiedad): bool
    {
        return $user->publicaPropiedades() && $propiedad->esDelUsuario($user);
    }
}
