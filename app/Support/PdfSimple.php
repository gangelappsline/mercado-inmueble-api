<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generador de PDF mínimo y sin dependencias externas (fuente Helvetica base 14).
 *
 * Se usa para el endpoint de exportación de reportes (`formato=pdf`) sin
 * arrastrar librerías de terceros: escribe un PDF 1.4 válido con tabla de
 * referencias cruzadas y soporte multi-página.
 */
final class PdfSimple
{
    /**
     * Ancho y alto de página A4 en puntos.
     */
    private const ANCHO = 595;

    private const ALTO = 842;

    private const MARGEN = 45;

    private const INTERLINEADO = 16;

    /**
     * Líneas por página (dejando espacio para el encabezado).
     */
    private const LINEAS_POR_PAGINA = 45;

    /**
     * Construye el PDF a partir de un título y líneas de texto.
     *
     * @param  array<int, string>  $lineas
     */
    public static function generar(string $titulo, array $lineas, ?string $pieDePagina = null): string
    {
        $paginas = array_chunk($lineas, self::LINEAS_POR_PAGINA) ?: [[]];
        $objetos = [];
        $contenidos = [];

        foreach ($paginas as $indice => $lineasDeLaPagina) {
            $contenido = self::flujoDePagina($titulo, $lineasDeLaPagina, $indice + 1, count($paginas), $pieDePagina);
            $contenidos[] = $contenido;
        }

        // 1: catálogo, 2: páginas, 3: fuente; luego cada página y su contenido.
        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[2] = ''; // se completa al final con las referencias
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $referenciasPaginas = [];
        $numero = 5;

        foreach ($contenidos as $contenido) {
            $idContenido = $numero++;
            $idPagina = $numero++;

            $objetos[$idContenido] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($contenido), $contenido);
            $objetos[$idPagina] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::ANCHO,
                self::ALTO,
                $idContenido,
            );

            $referenciasPaginas[] = $idPagina.' 0 R';
        }

        ksort($objetos);

        $objetos[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $referenciasPaginas), count($paginas));

        ksort($objetos);

        return self::ensamblar($objetos);
    }

    /**
     * Contenido (flujo PDF) de una página.
     *
     * @param  array<int, string>  $lineas
     */
    private static function flujoDePagina(string $titulo, array $lineas, int $pagina, int $total, ?string $pieDePagina): string
    {
        $y = self::ALTO - self::MARGEN;
        $partes = [];

        $partes[] = self::texto($titulo, $y, 16, true);
        $y -= self::INTERLINEADO;

        if ($pagina === 1) {
            $partes[] = self::texto('Generado el '.now()->translatedFormat('d \d\e F \d\e Y H:i'), $y, 9);
        }

        $y -= self::INTERLINEADO * 1.5;

        foreach ($lineas as $linea) {
            $partes[] = self::texto($linea, $y, 10);
            $y -= self::INTERLINEADO;
        }

        $partes[] = self::texto(
            $pieDePagina !== null ? sprintf('%s · Página %d/%d', $pieDePagina, $pagina, $total) : sprintf('Página %d/%d', $pagina, $total),
            self::MARGEN,
            8,
        );

        return implode("\n", $partes);
    }

    /**
     * Operador de texto PDF posicionado.
     */
    private static function texto(string $texto, float $y, int $tamanio, bool $negrita = false): string
    {
        return sprintf(
            'BT /%s %d Tf %d %.2F Td (%s) Tj ET',
            $negrita ? 'F2' : 'F1',
            $tamanio,
            self::MARGEN,
            $y,
            self::escapar($texto),
        );
    }

    /**
     * Escapa caracteres especiales y convierte a WinAnsi (Latin-1 ampliado).
     */
    private static function escapar(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $texto);

        $texto = $convertido === false ? $texto : $convertido;

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $texto);
    }

    /**
     * Ensambla los objetos, la tabla `xref` y el `trailer`.
     *
     * @param  array<int, string>  $objetos
     */
    private static function ensamblar(array $objetos): string
    {
        $pdf = "%PDF-1.4\n";
        $posiciones = [];

        foreach ($objetos as $numero => $cuerpo) {
            $posiciones[$numero] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $numero, $cuerpo);
        }

        $inicioXref = strlen($pdf);
        $total = max(array_keys($objetos)) + 1;

        $pdf .= sprintf("xref\n0 %d\n", $total);
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $total; $i++) {
            $pdf .= isset($posiciones[$i])
                ? sprintf("%010d 00000 n \n", $posiciones[$i])
                : "0000000000 65535 f \n";
        }

        $pdf .= sprintf(
            "trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF",
            $total,
            $inicioXref,
        );

        return $pdf;
    }
}
