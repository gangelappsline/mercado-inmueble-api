<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generador de hojas de cálculo en formato SpreadsheetML 2003 (`.xls`), que
 * Excel, LibreOffice y Google Sheets abren sin librerías externas.
 */
final class ExcelSimple
{
    /**
     * Construye el documento a partir de los encabezados y las filas.
     *
     * @param  array<int, string>  $encabezados
     * @param  array<int, array<int, string|int|float|null>>  $filas
     * @param  string  $hoja  Nombre de la hoja.
     */
    public static function generar(array $encabezados, array $filas, string $hoja = 'Reporte'): string
    {
        $documento = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $documento .= '<?mso-application progid="Excel.Sheet"?>'."\n";
        $documento .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            .'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'."\n";
        $documento .= '<Styles><Style ss:ID="cabecera"><Font ss:Bold="1"/>'
            .'<Interior ss:Color="#DCE6F1" ss:Pattern="Solid"/></Style></Styles>'."\n";
        $documento .= sprintf('<Worksheet ss:Name="%s"><Table>'."\n", self::escapar(self::recortar($hoja, 31)));

        if ($encabezados !== []) {
            $documento .= '<Row>';
            foreach ($encabezados as $encabezado) {
                $documento .= sprintf(
                    '<Cell ss:StyleID="cabecera"><Data ss:Type="String">%s</Data></Cell>',
                    self::escapar((string) $encabezado),
                );
            }
            $documento .= '</Row>'."\n";
        }

        foreach ($filas as $fila) {
            $documento .= '<Row>';
            foreach ($fila as $celda) {
                $documento .= self::celda($celda);
            }
            $documento .= '</Row>'."\n";
        }

        $documento .= "</Table></Worksheet></Workbook>\n";

        return $documento;
    }

    /**
     * Convierte el CSV de datos en una cadena lista para descargar.
     *
     * @param  array<int, string>  $encabezados
     * @param  array<int, array<int, string|int|float|null>>  $filas
     */
    public static function csv(array $encabezados, array $filas, string $delimitador = ';'): string
    {
        $salida = fopen('php://temp', 'r+');

        // BOM para que Excel respete los acentos.
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, $encabezados, $delimitador);

        foreach ($filas as $fila) {
            fputcsv($salida, array_map(static fn (mixed $valor): string => (string) $valor, $fila), $delimitador);
        }

        rewind($salida);
        $contenido = (string) stream_get_contents($salida);
        fclose($salida);

        return $contenido;
    }

    /**
     * Celda SpreadsheetML con el tipo de dato adecuado.
     */
    private static function celda(string|int|float|null $valor): string
    {
        if ($valor === null || $valor === '') {
            return '<Cell/>';
        }

        if (is_int($valor) || is_float($valor)) {
            return sprintf('<Cell><Data ss:Type="Number">%s</Data></Cell>', $valor);
        }

        return sprintf('<Cell><Data ss:Type="String">%s</Data></Cell>', self::escapar($valor));
    }

    /**
     * Escapa entidades XML y descarta caracteres de control.
     */
    private static function escapar(string $texto): string
    {
        return htmlspecialchars(
            preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto) ?? $texto,
            ENT_QUOTES | ENT_XML1,
            'UTF-8',
        );
    }

    /**
     * Recorta un texto multibyte.
     */
    private static function recortar(string $texto, int $largo): string
    {
        return mb_substr($texto, 0, $largo);
    }
}
