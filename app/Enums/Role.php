<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles de la plataforma. Un usuario tiene exactamente un rol y su perfil
 * extendido (Inmobiliaria|Vendedor|Cliente) se resuelve por relación polimórfica.
 */
enum Role: string
{
    case Inmobiliaria = 'inmobiliaria';
    case Vendedor = 'vendedor';
    case Cliente = 'cliente';

    /**
     * Etiqueta legible del rol.
     */
    public function label(): string
    {
        return match ($this) {
            self::Inmobiliaria => 'Inmobiliaria',
            self::Vendedor => 'Vendedor',
            self::Cliente => 'Cliente',
        };
    }

    /**
     * Indica si el rol puede publicar propiedades.
     */
    public function publicaPropiedades(): bool
    {
        return $this !== self::Cliente;
    }

    /**
     * Nombre del scope OAuth asociado al rol.
     */
    public function scope(): string
    {
        return $this->value;
    }

    /**
     * Valores válidos (para reglas de validación `in:`).
     *
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
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
}
