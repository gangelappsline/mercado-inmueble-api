<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles de la plataforma. Un usuario tiene exactamente un rol y su perfil
 * extendido (Inmobiliaria|Vendedor|Cliente) se resuelve por relación polimórfica.
 *
 * El rol `administrador` es transversal: no publica propiedades ni participa en
 * los hilos de conversación, y gestiona las cuentas, los anunciantes y la
 * moderación del catálogo desde el panel `/api/v1/admin/*`. Por seguridad no
 * admite registro público (se crea desde el panel o con `mercado:crear-admin`).
 *
 * Nota: `Administrador` se declara al final para que el `ENUM` de la columna
 * `users.role` sólo agregue un valor (MySQL puede alterar la columna sin
 * reconstruir la tabla cuando los valores existentes conservan su posición).
 */
enum Role: string
{
    case Inmobiliaria = 'inmobiliaria';
    case Vendedor = 'vendedor';
    case Cliente = 'cliente';
    case Administrador = 'administrador';

    /**
     * Etiqueta legible del rol.
     */
    public function label(): string
    {
        return match ($this) {
            self::Inmobiliaria => 'Inmobiliaria',
            self::Vendedor => 'Vendedor',
            self::Cliente => 'Cliente',
            self::Administrador => 'Administrador',
        };
    }

    /**
     * Indica si el rol puede publicar propiedades.
     */
    public function publicaPropiedades(): bool
    {
        return $this->esAnunciante();
    }

    /**
     * Indica si el rol es un anunciante (dueño de propiedades).
     */
    public function esAnunciante(): bool
    {
        return $this === self::Inmobiliaria || $this === self::Vendedor;
    }

    /**
     * Indica si el rol pertenece al equipo interno de la plataforma.
     */
    public function esStaff(): bool
    {
        return $this === self::Administrador;
    }

    /**
     * Indica si el rol puede crearse desde el registro público
     * (`POST /auth/register/{rol}`). Los administradores se crean únicamente
     * desde el panel de administración o por consola.
     */
    public function permiteRegistroPublico(): bool
    {
        return $this !== self::Administrador;
    }

    /**
     * Indica si el rol participa en los hilos de mensajes (cliente ↔ anunciante).
     */
    public function participaEnMensajes(): bool
    {
        return $this !== self::Administrador;
    }

    /**
     * Nombre del scope OAuth asociado al rol.
     */
    public function scope(): string
    {
        return $this->value;
    }

    /**
     * Valores válidos (para reglas de validación `in:` y columnas `ENUM`).
     *
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Roles que pueden registrarse públicamente.
     *
     * @return array<int, string>
     */
    public static function valoresRegistrables(): array
    {
        return array_values(array_filter(
            self::valores(),
            static fn (string $valor): bool => self::from($valor)->permiteRegistroPublico(),
        ));
    }

    /**
     * Roles que participan en los hilos de conversación (`mensajes.rol_autor`).
     *
     * @return array<int, string>
     */
    public static function valoresParticipantes(): array
    {
        return array_values(array_filter(
            self::valores(),
            static fn (string $valor): bool => self::from($valor)->participaEnMensajes(),
        ));
    }

    /**
     * Catálogo valor => etiqueta.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $rol): array => [$rol->value => $rol->label()])
            ->all();
    }

    /**
     * Catálogo valor => etiqueta de los roles registrables públicamente
     * (usado por el formulario de registro del frontend).
     *
     * @return array<string, string>
     */
    public static function opcionesRegistrables(): array
    {
        return array_intersect_key(self::opciones(), array_flip(self::valoresRegistrables()));
    }
}
