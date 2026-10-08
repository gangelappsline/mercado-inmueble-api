<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Formatos soportados por los endpoints de exportación de reportes.
 */
enum FormatoExportacion: string
{
    case Pdf = 'pdf';
    case Excel = 'excel';
    case Csv = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::Pdf => 'PDF',
            self::Excel => 'Excel',
            self::Csv => 'CSV',
        };
    }

    /**
     * Extensión del archivo generado.
     */
    public function extension(): string
    {
        return match ($this) {
            self::Pdf => 'pdf',
            self::Excel => 'xls',
            self::Csv => 'csv',
        };
    }

    /**
     * MIME type de la respuesta.
     */
    public function mime(): string
    {
        return match ($this) {
            self::Pdf => 'application/pdf',
            self::Excel => 'application/vnd.ms-excel',
            self::Csv => 'text/csv; charset=UTF-8',
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
            ->mapWithKeys(static fn (self $formato): array => [$formato->value => $formato->label()])
            ->all();
    }
}
