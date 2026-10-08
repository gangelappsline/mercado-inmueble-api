<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Métricas registradas por propiedad y día (tabla de agregados `reportes`).
 */
enum TipoReporte: string
{
    case Vista = 'vista';
    case Contacto = 'contacto';
    case Favorito = 'favorito';
    case Cita = 'cita';
    case Conversion = 'conversion';

    public function label(): string
    {
        return match ($this) {
            self::Vista => 'Vista',
            self::Contacto => 'Contacto',
            self::Favorito => 'Favorito',
            self::Cita => 'Cita generada',
            self::Conversion => 'Conversión',
        };
    }

    /**
     * Métricas que cuentan como "interacción" del embudo comercial.
     */
    public function esInteraccion(): bool
    {
        return $this !== self::Vista;
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
