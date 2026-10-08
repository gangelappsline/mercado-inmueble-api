<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida de una publicación.
 */
enum EstadoPropiedad: string
{
    case Borrador = 'borrador';
    case Publicada = 'publicada';
    case Pausada = 'pausada';
    case Vendida = 'vendida';
    case Alquilada = 'alquilada';
    case Rechazada = 'rechazada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Publicada => 'Publicada',
            self::Pausada => 'Pausada',
            self::Vendida => 'Vendida',
            self::Alquilada => 'Alquilada',
            self::Rechazada => 'Rechazada',
        };
    }

    /**
     * Sólo las propiedades publicadas son visibles en el catálogo público.
     */
    public function esVisiblePublicamente(): bool
    {
        return $this === self::Publicada;
    }

    /**
     * Estados que cierran la operación (se usan en el reporte de ingresos).
     */
    public function esCerrada(): bool
    {
        return in_array($this, [self::Vendida, self::Alquilada], true);
    }

    /**
     * Estados desde los que se puede publicar.
     */
    public function sePuedePublicar(): bool
    {
        return in_array($this, [self::Borrador, self::Pausada, self::Rechazada], true);
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
