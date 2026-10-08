<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\EstadoPropiedad;
use App\Models\Propiedad;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Implementación Eloquent del catálogo de propiedades.
 *
 * Decisiones de rendimiento:
 *  - Eager loading fijo de las relaciones que consumen los API Resources.
 *  - `withCount`/`withExists` evitan consultas N+1 al calcular favoritos.
 *  - La búsqueda por cercanía usa bounding box + Haversine (índice por lat/lng).
 */
final class PropiedadRepository implements PropiedadRepositoryInterface
{
    /**
     * Relaciones cargadas siempre en el catálogo público.
     *
     * @var array<int, string>
     */
    private const RELACIONES_CATALOGO = [
        'fotoPrincipal',
        'amenidades:id,nombre,slug,icono,categoria',
        'propietario',
    ];

    /**
     * Relaciones cargadas en el detalle de una propiedad.
     *
     * @var array<int, string>
     */
    private const RELACIONES_DETALLE = [
        'fotos',
        'video',
        'amenidades',
        'propietario',
    ];

    /**
     * {@inheritDoc}
     */
    public function catalogar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = Propiedad::query()
            ->publicadas()
            ->with(self::RELACIONES_CATALOGO)
            ->withCount(['favoritos', 'intereses']);

        $this->aplicarFiltros($query, $filtros);

        // Orden por distancia sólo cuando el motor puede calcularla en SQL.
        if ($this->ordenarPorDistancia($filtros)) {
            $query->orderBy('distancia');
        } else {
            $query->ordenar($this->cadena($filtros['orden'] ?? null));
        }

        $paginado = $query->paginate(
            perPage: $porPagina,
            page: $this->entero($filtros['page'] ?? null, 1),
        )->withQueryString();

        return $this->calcularDistanciaEnMemoria($paginado, $filtros);
    }

    /**
     * {@inheritDoc}
     */
    public function listarParaPropietario(Model $propietario, array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = Propiedad::query()
            ->delPropietario($propietario)
            ->with(['fotoPrincipal', 'amenidades', 'video'])
            ->withCount(['intereses', 'citas', 'favoritos']);

        $this->aplicarFiltros($query, $filtros, publicas: false);

        if (filled($filtros['estado'] ?? null)) {
            $query->where('estado', $filtros['estado']);
        }

        $query->ordenar($this->cadena($filtros['orden'] ?? null) ?? 'recientes');

        return $query->paginate(
            perPage: $porPagina,
            page: $this->entero($filtros['page'] ?? null, 1),
        )->withQueryString();
    }

    /**
     * {@inheritDoc}
     */
    public function listarParaAdministracion(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = Propiedad::query()
            ->with(['fotoPrincipal', 'propietario', 'video'])
            ->withCount(['intereses', 'citas', 'favoritos']);

        $this->aplicarFiltros($query, $filtros, publicas: false);

        if (filled($filtros['estado'] ?? null)) {
            $query->where('estado', $filtros['estado']);
        }

        if (filled($filtros['propietario_tipo'] ?? null)) {
            $query->where('propietario_type', (string) $filtros['propietario_tipo']);
        }

        if (filled($filtros['propietario_id'] ?? null)) {
            $query->where('propietario_id', (int) $filtros['propietario_id']);
        }

        $query->orderByDesc('destacada')
            ->ordenar($this->cadena($filtros['orden'] ?? null) ?? 'recientes');

        return $query->paginate(
            perPage: $porPagina,
            page: $this->entero($filtros['page'] ?? null, 1),
        )->withQueryString();
    }

    /**
     * {@inheritDoc}
     */
    public function destacadas(int $limite = 8): Collection
    {
        return Propiedad::query()
            ->publicadas()
            ->destacadas()
            ->with(self::RELACIONES_CATALOGO)
            ->orderByDesc('publicada_en')
            ->limit($limite)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function similares(Propiedad $propiedad, int $limite = 6): Collection
    {
        $tolerancia = (float) $propiedad->precio * 0.3;

        return Propiedad::query()
            ->publicadas()
            ->whereKeyNot($propiedad->getKey())
            ->where('operacion', $propiedad->operacion->value)
            ->where('tipo', $propiedad->tipo->value)
            ->where(function (Builder $query) use ($propiedad, $tolerancia): void {
                $query->where('ciudad', $propiedad->ciudad)
                    ->orWhereBetween('precio', [
                        max((float) $propiedad->precio - $tolerancia, 0),
                        (float) $propiedad->precio + $tolerancia,
                    ]);
            })
            ->with(self::RELACIONES_CATALOGO)
            ->orderByDesc('destacada')
            ->orderByDesc('vistas_count')
            ->limit($limite)
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function resumenPorEstado(Model $propietario): array
    {
        $conteos = Propiedad::query()
            ->delPropietario($propietario)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        $resumen = [];
        $total = 0;

        foreach (EstadoPropiedad::cases() as $estado) {
            $cantidad = (int) ($conteos[$estado->value] ?? 0);
            $resumen[$estado->value] = $cantidad;
            $total += $cantidad;
        }

        $resumen['total'] = $total;

        return $resumen;
    }

    /**
     * {@inheritDoc}
     */
    public function resumenGlobalPorEstado(): array
    {
        $conteos = Propiedad::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        $resumen = [];
        $total = 0;

        foreach (EstadoPropiedad::cases() as $estado) {
            $cantidad = (int) ($conteos[$estado->value] ?? 0);
            $resumen[$estado->value] = $cantidad;
            $total += $cantidad;
        }

        $resumen['total'] = $total;
        $resumen['destacadas'] = Propiedad::query()->destacadas()->count();

        return $resumen;
    }

    /**
     * {@inheritDoc}
     */
    public function ciudadesConConteo(): Collection
    {
        return Propiedad::query()
            ->publicadas()
            ->selectRaw('ciudad, estado_provincia, pais, COUNT(*) as propiedades_count')
            ->groupBy('ciudad', 'estado_provincia', 'pais')
            ->orderByDesc('propiedades_count')
            ->get();
    }

    /**
     * Detalle con todas las relaciones necesarias para PropiedadDetalleResource.
     */
    public function detalle(Propiedad $propiedad): Propiedad
    {
        return $propiedad->load(self::RELACIONES_DETALLE)
            ->loadCount(['favoritos', 'intereses', 'citas']);
    }

    /**
     * Aplica los filtros del query string al builder.
     *
     * @param  Builder<Propiedad>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $query, array $filtros, bool $publicas = true): void
    {
        $query
            ->buscar($this->cadena($filtros['q'] ?? null))
            ->deTipo($filtros['tipo'] ?? null)
            ->deOperacion($filtros['operacion'] ?? null)
            ->enCiudad($this->cadena($filtros['ciudad'] ?? null))
            ->enEstadoProvincia($this->cadena($filtros['estado_provincia'] ?? null))
            ->enRangoDePrecio(
                $filtros['precio_min'] ?? null,
                $filtros['precio_max'] ?? null,
                $filtros['moneda'] ?? null,
            )
            ->conHabitacionesMinimas($filtros['habitaciones_min'] ?? null)
            ->conBanosMinimos($filtros['banos_min'] ?? null)
            ->conEstacionamientosMinimos($filtros['estacionamientos_min'] ?? null)
            ->conAreaMinima($filtros['area_min'] ?? null);

        if (filter_var($filtros['destacada'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->destacadas();
        }

        if (filter_var($filtros['con_video'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->conVideo();
        }

        if ($publicas === false && filled($filtros['solo_publicadas'] ?? null)
            && filter_var($filtros['solo_publicadas'], FILTER_VALIDATE_BOOLEAN)) {
            $query->publicadas();
        }

        $amenidades = $this->listaDeIds($filtros['amenidades'] ?? null);

        if ($amenidades !== []) {
            $todas = filter_var($filtros['amenidades_todas'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $todas ? $query->conTodasLasAmenidades($amenidades) : $query->conAlgunaAmenidad($amenidades);
        }

        $this->aplicarFiltroGeografico($query, $filtros);
    }

    /**
     * Filtro `cerca_de` (lat,lng[,radio]) o `latitud`+`longitud`+`radio_km`.
     *
     * @param  Builder<Propiedad>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltroGeografico(Builder $query, array $filtros): void
    {
        $centro = $this->resolverCentro($filtros);

        if ($centro === null) {
            return;
        }

        $query->cercaDe(
            lat: $centro['lat'],
            lng: $centro['lng'],
            radio: $filtros['radio_km'] ?? $filtros['radio'] ?? null,
            unidad: (string) config('mercado.geo.unidad', 'km'),
        );
    }

    /**
     * Normaliza las distintas formas de enviar coordenadas.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{lat: float, lng: float}|null
     */
    private function resolverCentro(array $filtros): ?array
    {
        $cercaDe = $filtros['cerca_de'] ?? null;

        if (is_string($cercaDe) && str_contains($cercaDe, ',')) {
            [$lat, $lng] = array_pad(explode(',', $cercaDe, 3), 2, null);

            if (Geo::coordenadasValidas($lat, $lng)) {
                return ['lat' => (float) $lat, 'lng' => (float) $lng];
            }
        }

        if (is_array($cercaDe) && isset($cercaDe['lat'], $cercaDe['lng'])) {
            if (Geo::coordenadasValidas($cercaDe['lat'], $cercaDe['lng'])) {
                return ['lat' => (float) $cercaDe['lat'], 'lng' => (float) $cercaDe['lng']];
            }
        }

        if (Geo::coordenadasValidas($filtros['latitud'] ?? null, $filtros['longitud'] ?? null)) {
            return ['lat' => (float) $filtros['latitud'], 'lng' => (float) $filtros['longitud']];
        }

        return null;
    }

    /**
     * ¿Se pidió ordenar por distancia y el motor soporta el cálculo en SQL?
     *
     * @param  array<string, mixed>  $filtros
     */
    private function ordenarPorDistancia(array $filtros): bool
    {
        return ($filtros['orden'] ?? null) === 'distancia'
            && $this->resolverCentro($filtros) !== null
            && Geo::driverSoportaMatematicas((new Propiedad)->getConnection()->getDriverName());
    }

    /**
     * En motores sin funciones trigonométricas (SQLite en tests) se calcula la
     * distancia en PHP para que la respuesta siga trayendo `distancia`.
     *
     * @param  LengthAwarePaginator<int, Propiedad>  $paginado
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Propiedad>
     */
    private function calcularDistanciaEnMemoria(LengthAwarePaginator $paginado, array $filtros): LengthAwarePaginator
    {
        $centro = $this->resolverCentro($filtros);

        if ($centro === null || Geo::driverSoportaMatematicas((new Propiedad)->getConnection()->getDriverName())) {
            return $paginado;
        }

        $unidad = (string) config('mercado.geo.unidad', 'km');

        $paginado->getCollection()->each(function (Propiedad $propiedad) use ($centro, $unidad): void {
            $propiedad->setAttribute(
                'distancia',
                $propiedad->distanciaDesde($centro['lat'], $centro['lng'], $unidad),
            );
        });

        return $paginado;
    }

    /**
     * @param  mixed  $valor
     */
    private function cadena(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    /**
     * Normaliza "1,2,3" | [1,2,3] | 1 a lista de enteros.
     *
     * @return array<int, int>
     */
    private function listaDeIds(mixed $valor): array
    {
        if ($valor === null || $valor === '') {
            return [];
        }

        $valores = is_array($valor) ? $valor : explode(',', (string) $valor);

        return collect($valores)
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function entero(mixed $valor, int $porDefecto): int
    {
        return is_numeric($valor) && (int) $valor > 0 ? (int) $valor : $porDefecto;
    }
}
