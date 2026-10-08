<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Cita;
use App\Models\Inmobiliaria;
use App\Models\Propiedad;
use App\Models\Reporte;
use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contrato común de los perfiles que pueden publicar propiedades
 * (Inmobiliaria y Vendedor). Permite que servicios y notificaciones trabajen
 * con ambos sin condicionales por tipo.
 */
interface PropietarioDePropiedades
{
    /**
     * Usuario dueño de la cuenta.
     *
     * @return BelongsTo<User, covariant Inmobiliaria|Vendedor>
     */
    public function user(): BelongsTo;

    /**
     * Propiedades publicadas por el perfil.
     *
     * @return MorphMany<Propiedad, covariant Inmobiliaria|Vendedor>
     */
    public function propiedades(): MorphMany;

    /**
     * Citas agendadas con el perfil.
     *
     * @return MorphMany<Cita, covariant Inmobiliaria|Vendedor>
     */
    public function citas(): MorphMany;

    /**
     * Métricas diarias del perfil.
     *
     * @return MorphMany<Reporte, covariant Inmobiliaria|Vendedor>
     */
    public function reportes(): MorphMany;

    /**
     * Nombre con el que se muestra el anunciante en la API.
     */
    public function nombreComercial(): string;

    /**
     * Correo de contacto para notificaciones.
     */
    public function emailContacto(): string;

    /**
     * Teléfono de contacto público.
     */
    public function telefonoContacto(): ?string;

    /**
     * Ruta pública del logotipo/foto del anunciante.
     */
    public function urlLogo(): ?string;
}
