<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Propiedad;
use App\Models\User;

/**
 * Autorización de reportes: sólo los anunciantes consultan las métricas de sus
 * propias propiedades.
 */
class ReportePolicy
{
    /**
     * Ver los reportes del panel (resumen, conversión, ingresos).
     */
    public function viewAny(User $user): bool
    {
        return $user->publicaPropiedades() && $user->propietario() !== null;
    }

    /**
     * Exportar reportes en PDF, Excel o CSV.
     */
    public function exportar(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Ver las métricas de una propiedad concreta.
     */
    public function verPropiedad(User $user, Propiedad $propiedad): bool
    {
        return $user->publicaPropiedades() && $propiedad->esDelUsuario($user);
    }
}
