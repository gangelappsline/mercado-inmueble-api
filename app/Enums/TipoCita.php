<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Modalidad de la cita.
 */
enum TipoCita: string
{
    case Visita = 'visita';
    case Virtual = 'virtual';
    case Llamada = 'llamada';

    public function label(): string
    {
        return match ($this) {
            self::Visita => 'Visita presencial',
            self::Virtual => 'Visita virtual',
            self::Llamada => 'Llamada telefónica',
        };
    }

    /**
     * Las citas virtuales requieren enlace de videollamada.
     */
    public function requiereEnlace(): bool
    {
        return $this === self::Virtual;
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
