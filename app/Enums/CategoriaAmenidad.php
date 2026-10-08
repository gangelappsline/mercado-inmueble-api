<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Agrupación del catálogo de amenidades.
 */
enum CategoriaAmenidad: string
{
    case Interior = 'interior';
    case Exterior = 'exterior';
    case Comunidad = 'comunidad';
    case Seguridad = 'seguridad';
    case Servicios = 'servicios';

    public function label(): string
    {
        return match ($this) {
            self::Interior => 'Interior',
            self::Exterior => 'Exterior',
            self::Comunidad => 'Áreas comunes',
            self::Seguridad => 'Seguridad',
            self::Servicios => 'Servicios',
        };
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> */
    public static function opciones(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $categoria): array => [$categoria->value => $categoria->label()])
            ->all();
    }
}
