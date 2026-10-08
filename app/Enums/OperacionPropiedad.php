<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Operación comercial de una propiedad.
 */
enum OperacionPropiedad: string
{
    case Venta = 'venta';
    case Renta = 'renta';
    case Anticretico = 'anticretico';

    public function label(): string
    {
        return match ($this) {
            self::Venta => 'Venta',
            self::Renta => 'Renta',
            self::Anticretico => 'Anticrético',
        };
    }

    /**
     * Las operaciones de renta usan precio mensual.
     */
    public function esRecurrente(): bool
    {
        return $this !== self::Venta;
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
            ->mapWithKeys(static fn (self $op): array => [$op->value => $op->label()])
            ->all();
    }
}
