<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Monedas soportadas para precios y presupuestos.
 */
enum Moneda: string
{
    case Bob = 'BOB';
    case Usd = 'USD';
    case Eur = 'EUR';
    case Pen = 'PEN';
    case Ars = 'ARS';
    case Mxn = 'MXN';
    case Cop = 'COP';
    case Clp = 'CLP';
    case Pyg = 'PYG';
    case Uyu = 'UYU';

    public function label(): string
    {
        return match ($this) {
            self::Bob => 'Boliviano',
            self::Usd => 'Dólar estadounidense',
            self::Eur => 'Euro',
            self::Pen => 'Sol peruano',
            self::Ars => 'Peso argentino',
            self::Mxn => 'Peso mexicano',
            self::Cop => 'Peso colombiano',
            self::Clp => 'Peso chileno',
            self::Pyg => 'Guaraní',
            self::Uyu => 'Peso uruguayo',
        };
    }

    /**
     * Símbolo para formateo de precios.
     */
    public function simbolo(): string
    {
        return match ($this) {
            self::Bob => 'Bs.',
            self::Usd => '$us',
            self::Eur => '€',
            self::Pen => 'S/',
            self::Ars => '$',
            self::Mxn => '$',
            self::Cop => '$',
            self::Clp => '$',
            self::Pyg => '₲',
            self::Uyu => '$U',
        };
    }

    /**
     * Formatea un monto con el símbolo de la moneda.
     */
    public function formatear(float|int|string $monto): string
    {
        return sprintf('%s %s', $this->simbolo(), number_format((float) $monto, 2, ',', '.'));
    }

    public static function porDefecto(): self
    {
        return self::tryFrom((string) config('mercado.moneda_por_defecto', 'BOB')) ?? self::Bob;
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
            ->mapWithKeys(static fn (self $moneda): array => [$moneda->value => $moneda->label()])
            ->all();
    }
}
