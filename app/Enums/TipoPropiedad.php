<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipos de inmueble que acepta el catálogo.
 */
enum TipoPropiedad: string
{
    case Casa = 'casa';
    case Departamento = 'departamento';
    case Terreno = 'terreno';
    case LocalComercial = 'local_comercial';
    case Oficina = 'oficina';
    case Galpon = 'galpon';
    case Almacen = 'almacen';
    case Edificio = 'edificio';
    case Finca = 'finca';
    case Habitacion = 'habitacion';
    case Parqueo = 'parqueo';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Casa => 'Casa',
            self::Departamento => 'Departamento',
            self::Terreno => 'Terreno',
            self::LocalComercial => 'Local comercial',
            self::Oficina => 'Oficina',
            self::Galpon => 'Galpón',
            self::Almacen => 'Almacén',
            self::Edificio => 'Edificio',
            self::Finca => 'Finca',
            self::Habitacion => 'Habitación',
            self::Parqueo => 'Parqueo',
            self::Otro => 'Otro',
        };
    }

    /**
     * Los terrenos no manejan habitaciones, baños ni estacionamientos.
     */
    public function tieneAmbientes(): bool
    {
        return ! in_array($this, [self::Terreno, self::Finca, self::Parqueo], true);
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
            ->mapWithKeys(static fn (self $tipo): array => [$tipo->value => $tipo->label()])
            ->all();
    }
}
