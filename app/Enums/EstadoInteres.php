<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estados del embudo de interesados.
 */
enum EstadoInteres: string
{
    case Nuevo = 'nuevo';
    case EnRevision = 'en_revision';
    case Atendido = 'atendido';
    case Cerrado = 'cerrado';
    case Descartado = 'descartado';

    public function label(): string
    {
        return match ($this) {
            self::Nuevo => 'Nuevo',
            self::EnRevision => 'En revisión',
            self::Atendido => 'Atendido',
            self::Cerrado => 'Cerrado',
            self::Descartado => 'Descartado',
        };
    }

    /**
     * Estados finales del embudo (no admiten más cambios).
     */
    public function esFinal(): bool
    {
        return in_array($this, [self::Cerrado, self::Descartado], true);
    }

    /**
     * Cuenta como conversión en los reportes.
     */
    public function esConversion(): bool
    {
        return $this === self::Cerrado;
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
            ->mapWithKeys(static fn (self $estado): array => [$estado->value => $estado->label()])
            ->all();
    }
}
