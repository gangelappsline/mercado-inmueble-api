<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Utilidades de geolocalización. La distancia exacta se calcula con la fórmula
 * de Haversine; en SQL siempre se aplica antes una "bounding box" para que el
 * índice (latitud, longitud) se use y la consulta no haga un full scan.
 */
final class Geo
{
    /**
     * Drivers cuyo motor incluye funciones trigonométricas en SQL.
     * En SQLite (usado por los tests) sólo se aplica la bounding box.
     */
    private const DRIVERS_CON_MATEMATICAS = ['mysql', 'mariadb', 'pgsql'];

    /**
     * Radio medio de la Tierra en kilómetros.
     */
    public const RADIO_TIERRA_KM = 6371.0;

    /**
     * Distancia en kilómetros entre dos coordenadas (Haversine).
     */
    public static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round(self::RADIO_TIERRA_KM * 2 * asin(min(1.0, sqrt($a))), 3);
    }

    /**
     * Distancia en la unidad configurada (km o mi).
     */
    public static function distancia(float $lat1, float $lng1, float $lat2, float $lng2, string $unidad = 'km'): float
    {
        $km = self::distanciaKm($lat1, $lng1, $lat2, $lng2);

        return $unidad === 'mi' ? round($km / 1.609344, 3) : $km;
    }

    /**
     * Indica si el motor de base de datos puede evaluar la fórmula en SQL.
     */
    public static function driverSoportaMatematicas(?string $driver): bool
    {
        return in_array($driver, self::DRIVERS_CON_MATEMATICAS, true);
    }

    /**
     * Expresión SQL (Haversine) que devuelve la distancia en la unidad indicada.
     * Los valores de latitud/longitud se interpolan como literales numéricos
     * (nunca provienen de entrada del usuario sin castear a float).
     *
     * @param  string  $tabla  Tabla/alias de la propiedad (por defecto `propiedades`).
     */
    public static function expresionHaversine(
        float $lat,
        float $lng,
        string $unidad = 'km',
        string $tabla = 'propiedades',
    ): string {
        $factor = $unidad === 'mi' ? self::RADIO_TIERRA_KM / 1.609344 : self::RADIO_TIERRA_KM;

        return sprintf(
            '(%F * acos(least(1, greatest(-1, '
            .'cos(radians(%F)) * cos(radians(`%s`.`latitud`)) '
            .'* cos(radians(`%s`.`longitud`) - radians(%F)) '
            .'+ sin(radians(%F)) * sin(radians(`%s`.`latitud`))'
            .'))))',
            $factor,
            $lat,
            $tabla,
            $tabla,
            $lng,
            $lat,
            $tabla,
        );
    }

    /**
     * Caja delimitadora (bounding box) alrededor de un punto.
     *
     * @return array{lat_min: float, lat_max: float, lng_min: float, lng_max: float}
     */
    public static function cajaDelimitadora(float $lat, float $lng, float $radio, string $unidad = 'km'): array
    {
        $radioKm = $unidad === 'mi' ? $radio * 1.609344 : $radio;

        $deltaLat = $radioKm / 111.045;
        $deltaLng = $radioKm / (111.045 * max(cos(deg2rad($lat)), 0.0001));

        return [
            'lat_min' => round(max($lat - $deltaLat, -90.0), 7),
            'lat_max' => round(min($lat + $deltaLat, 90.0), 7),
            'lng_min' => round(max($lng - $deltaLng, -180.0), 7),
            'lng_max' => round(min($lng + $deltaLng, 180.0), 7),
        ];
    }

    /**
     * Valida que un par de coordenadas esté dentro de los rangos terrestres.
     */
    public static function coordenadasValidas(mixed $latitud, mixed $longitud): bool
    {
        if (! is_numeric($latitud) || ! is_numeric($longitud)) {
            return false;
        }

        return (float) $latitud >= -90 && (float) $latitud <= 90
            && (float) $longitud >= -180 && (float) $longitud <= 180;
    }

    /**
     * Centro geográfico por defecto (config/mercado.php) para búsquedas
     * sin coordenadas explícitas.
     *
     * @return array{latitud: float, longitud: float}
     */
    public static function centroPorDefecto(): array
    {
        return config('mercado.geo.centro_por_defecto');
    }
}
