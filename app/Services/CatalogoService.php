<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CategoriaAmenidad;
use App\Enums\EstadoCita;
use App\Enums\EstadoInteres;
use App\Enums\EstadoPropiedad;
use App\Enums\FormatoExportacion;
use App\Enums\Moneda;
use App\Enums\OperacionPropiedad;
use App\Enums\TipoCita;
use App\Enums\TipoPropiedad;
use App\Enums\TipoReporte;
use App\Models\Amenidad;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogos de la API (amenidades, ciudades, enums). Todo lo que cambia poco
 * se sirve desde caché para no golpear la base de datos en cada listado.
 */
final class CatalogoService
{
    public function __construct(
        private readonly PropiedadRepositoryInterface $propiedades,
    ) {
    }

    /**
     * Amenidades activas (catálogo cacheado en el modelo).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Amenidad>
     */
    public function amenidades()
    {
        return Amenidad::catalogo();
    }

    /**
     * Ciudades con publicaciones activas y su conteo.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function ciudades()
    {
        return Cache::remember(
            'catalogo.ciudades',
            (int) config('mercado.catalogo_cache_segundos', 3600),
            fn () => $this->propiedades->ciudadesConConteo()->map(static fn ($fila): array => [
                'ciudad' => $fila->ciudad,
                'estado_provincia' => $fila->estado_provincia,
                'pais' => $fila->pais,
                'propiedades_count' => (int) $fila->propiedades_count,
            ])->values(),
        );
    }

    /**
     * Catálogo completo de opciones para formularios del frontend.
     *
     * @return array<string, array<string, string>>
     */
    public function opciones(): array
    {
        return Cache::remember(
            'catalogo.opciones',
            (int) config('mercado.catalogo_cache_segundos', 3600),
            static fn (): array => [
                'tipos_propiedad' => TipoPropiedad::opciones(),
                'operaciones' => OperacionPropiedad::opciones(),
                'estados_propiedad' => EstadoPropiedad::opciones(),
                'monedas' => Moneda::opciones(),
                'tipos_cita' => TipoCita::opciones(),
                'estados_cita' => EstadoCita::opciones(),
                'estados_interes' => EstadoInteres::opciones(),
                'tipos_reporte' => TipoReporte::opciones(),
                'categorias_amenidad' => CategoriaAmenidad::opciones(),
                'formatos_exportacion' => FormatoExportacion::opciones(),
            ],
        );
    }

    /**
     * Invalida las cachés de catálogo (se llama al sembrar o al cambiar datos).
     */
    public function olvidar(): void
    {
        Amenidad::limpiarCache();
        Cache::forget('catalogo.ciudades');
        Cache::forget('catalogo.opciones');
    }
}
