<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\PropietarioDePropiedades;
use App\Enums\EstadoPropiedad;
use App\Enums\Moneda;
use App\Enums\OperacionPropiedad;
use App\Enums\TipoPropiedad;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\GeneraSlug;
use App\Support\Geo;
use Database\Factories\PropiedadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Propiedad publicada por una inmobiliaria o un vendedor.
 *
 * @property TipoPropiedad $tipo
 * @property OperacionPropiedad $operacion
 * @property EstadoPropiedad $estado
 * @property Moneda $moneda
 * @property-read Inmobiliaria|Vendedor|null $propietario
 */
class Propiedad extends Model
{
    /** @use HasFactory<PropiedadFactory> */
    use Auditable;
    use GeneraSlug;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'propiedades';

    /**
     * Atributos asignables. Los contadores (vistas_count, favoritos_count...)
     * y las marcas de tiempo de publicación NO son asignables: los controla el
     * servicio para que no puedan ser manipulados desde una petición.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'propietario_type',
        'propietario_id',
        'codigo',
        'slug',
        'titulo',
        'descripcion',
        'tipo',
        'operacion',
        'estado',
        'destacada',
        'precio',
        'moneda',
        'expensas',
        'precio_negociable',
        'area_total',
        'area_construida',
        'habitaciones',
        'banos',
        'estacionamientos',
        'piso',
        'anio_construccion',
        'amoblado',
        'direccion',
        'ciudad',
        'estado_provincia',
        'pais',
        'codigo_postal',
        'latitud',
        'longitud',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoPropiedad::class,
            'operacion' => OperacionPropiedad::class,
            'estado' => EstadoPropiedad::class,
            'moneda' => Moneda::class,
            'destacada' => 'boolean',
            'precio_negociable' => 'boolean',
            'amoblado' => 'boolean',
            'precio' => 'decimal:2',
            'expensas' => 'decimal:2',
            'area_total' => 'decimal:2',
            'area_construida' => 'decimal:2',
            'latitud' => 'decimal:7',
            'longitud' => 'decimal:7',
            'habitaciones' => 'integer',
            'banos' => 'integer',
            'estacionamientos' => 'integer',
            'piso' => 'integer',
            'anio_construccion' => 'integer',
            'vistas_count' => 'integer',
            'contactos_count' => 'integer',
            'favoritos_count' => 'integer',
            'citas_count' => 'integer',
            'publicada_en' => 'datetime',
            'vendida_en' => 'datetime',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function atributosAuditables(): array
    {
        return ['titulo', 'descripcion', 'tipo', 'operacion', 'estado', 'precio', 'moneda', 'direccion', 'ciudad', 'destacada'];
    }

    /**
     * Genera el código público y el slug antes de insertar.
     */
    protected static function booted(): void
    {
        static::creating(function (self $propiedad): void {
            $propiedad->codigo ??= self::generarCodigo();
            $propiedad->slug ??= self::slugUnico([$propiedad->titulo, $propiedad->ciudad]);
        });
    }

    /**
     * Código público correlativo legible: MI-2026-000123.
     */
    public static function generarCodigo(): string
    {
        return sprintf('MI-%s-%06d', now()->format('Y'), (int) static::withTrashed()->max('id') + 1);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relaciones
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Dueño de la publicación (inmobiliaria | vendedor).
     *
     * @return MorphTo<Model, $this>
     */
    public function propietario(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Fotos ordenadas por `orden`.
     *
     * @return HasMany<PropiedadFoto, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(PropiedadFoto::class)->orderBy('orden')->orderBy('id');
    }

    /**
     * Foto marcada como principal.
     *
     * @return HasOne<PropiedadFoto, $this>
     */
    public function fotoPrincipal(): HasOne
    {
        return $this->hasOne(PropiedadFoto::class)->where('es_principal', true);
    }

    /**
     * Video de la propiedad (máximo uno).
     *
     * @return HasOne<PropiedadVideo, $this>
     */
    public function video(): HasOne
    {
        return $this->hasOne(PropiedadVideo::class);
    }

    /**
     * @return BelongsToMany<Amenidad, $this>
     */
    public function amenidades(): BelongsToMany
    {
        return $this->belongsToMany(Amenidad::class, 'amenidad_propiedad')->withTimestamps();
    }

    /**
     * @return HasMany<Interes, $this>
     */
    public function intereses(): HasMany
    {
        return $this->hasMany(Interes::class);
    }

    /**
     * @return HasMany<Cita, $this>
     */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /**
     * @return HasMany<Favorito, $this>
     */
    public function favoritos(): HasMany
    {
        return $this->hasMany(Favorito::class);
    }

    /**
     * @return HasMany<Reporte, $this>
     */
    public function reportes(): HasMany
    {
        return $this->hasMany(Reporte::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes de filtrado (catálogo público y paneles)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Sólo publicaciones visibles en el catálogo público.
     *
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopePublicadas(Builder $query): Builder
    {
        return $query->where('estado', EstadoPropiedad::Publicada->value)
            ->whereNotNull('publicada_en')
            ->where('publicada_en', '<=', now());
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeDestacadas(Builder $query): Builder
    {
        return $query->where('destacada', true);
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('publicada_en')->orderByDesc('created_at');
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeDeOperacion(Builder $query, OperacionPropiedad|string|null $operacion): Builder
    {
        $valor = $operacion instanceof OperacionPropiedad ? $operacion->value : $operacion;

        return $query->when(filled($valor), fn (Builder $query) => $query->where('operacion', $valor));
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @param  TipoPropiedad|string|array<int, TipoPropiedad|string>|null  $tipo
     * @return Builder<Propiedad>
     */
    public function scopeDeTipo(Builder $query, TipoPropiedad|string|array|null $tipo): Builder
    {
        if ($tipo === null || $tipo === '') {
            return $query;
        }

        $valores = collect(is_array($tipo) ? $tipo : [$tipo])
            ->map(static fn (TipoPropiedad|string $t): string => $t instanceof TipoPropiedad ? $t->value : $t)
            ->all();

        return $query->whereIn('tipo', $valores);
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeEnCiudad(Builder $query, ?string $ciudad): Builder
    {
        return $query->when(filled($ciudad), static fn (Builder $query) => $query->where('ciudad', $ciudad));
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeEnEstadoProvincia(Builder $query, ?string $estadoProvincia): Builder
    {
        return $query->when(
            filled($estadoProvincia),
            static fn (Builder $query) => $query->where('estado_provincia', $estadoProvincia)
        );
    }

    /**
     * Rango de precio; si se envía moneda, se compara en esa moneda.
     *
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeEnRangoDePrecio(
        Builder $query,
        float|int|string|null $min = null,
        float|int|string|null $max = null,
        Moneda|string|null $moneda = null,
    ): Builder {
        $monedaValor = $moneda instanceof Moneda ? $moneda->value : $moneda;

        return $query
            ->when(is_numeric($min), static fn (Builder $query) => $query->where('precio', '>=', (float) $min))
            ->when(is_numeric($max), static fn (Builder $query) => $query->where('precio', '<=', (float) $max))
            ->when(filled($monedaValor), static fn (Builder $query) => $query->where('moneda', $monedaValor));
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeConHabitacionesMinimas(Builder $query, int|string|null $habitaciones): Builder
    {
        return $query->when(
            is_numeric($habitaciones),
            static fn (Builder $query) => $query->where('habitaciones', '>=', (int) $habitaciones)
        );
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeConBanosMinimos(Builder $query, int|string|null $banos): Builder
    {
        return $query->when(
            is_numeric($banos),
            static fn (Builder $query) => $query->where('banos', '>=', (int) $banos)
        );
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeConEstacionamientosMinimos(Builder $query, int|string|null $estacionamientos): Builder
    {
        return $query->when(
            is_numeric($estacionamientos),
            static fn (Builder $query) => $query->where('estacionamientos', '>=', (int) $estacionamientos)
        );
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeConAreaMinima(Builder $query, float|int|string|null $area): Builder
    {
        return $query->when(
            is_numeric($area),
            static fn (Builder $query) => $query->where('area_total', '>=', (float) $area)
        );
    }

    /**
     * La propiedad debe tener TODAS las amenidades indicadas.
     *
     * @param  Builder<Propiedad>  $query
     * @param  array<int, int|string>  $amenidadIds
     * @return Builder<Propiedad>
     */
    public function scopeConTodasLasAmenidades(Builder $query, array $amenidadIds): Builder
    {
        foreach (array_unique($amenidadIds) as $amenidadId) {
            $query->whereHas('amenidades', static fn (Builder $query) => $query->whereKey($amenidadId));
        }

        return $query;
    }

    /**
     * La propiedad tiene AL MENOS UNA de las amenidades indicadas.
     *
     * @param  Builder<Propiedad>  $query
     * @param  array<int, int|string>  $amenidadIds
     * @return Builder<Propiedad>
     */
    public function scopeConAlgunaAmenidad(Builder $query, array $amenidadIds): Builder
    {
        return $query->whereHas(
            'amenidades',
            static fn (Builder $query) => $query->whereIn('amenidades.id', $amenidadIds)
        );
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeConVideo(Builder $query): Builder
    {
        return $query->whereHas('video');
    }

    /**
     * Búsqueda textual portable (LIKE). En MySQL existe además el índice
     * fulltext `propiedades_busqueda_fulltext` para una optimización futura.
     *
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when(filled($termino), static function (Builder $query) use ($termino): void {
            $comodin = '%'.trim((string) $termino).'%';

            $query->where(static function (Builder $query) use ($comodin): void {
                $query->where('titulo', 'like', $comodin)
                    ->orWhere('descripcion', 'like', $comodin)
                    ->orWhere('direccion', 'like', $comodin)
                    ->orWhere('codigo', 'like', $comodin);
            });
        });
    }

    /**
     * Búsqueda por cercanía (Haversine) con pre-filtro por bounding box.
     *
     * En MySQL/PostgreSQL además se calcula la distancia exacta en SQL y se
     * expone como atributo `distancia`. En otros motores (SQLite en tests)
     * sólo se aplica la caja delimitadora.
     *
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeCercaDe(
        Builder $query,
        float $lat,
        float $lng,
        float|int|string|null $radio = null,
        string $unidad = 'km',
    ): Builder {
        $radio = is_numeric($radio) ? (float) $radio : (float) config('mercado.geo.radio_por_defecto_km');
        $caja = Geo::cajaDelimitadora($lat, $lng, $radio, $unidad);

        $query->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->whereBetween('latitud', [$caja['lat_min'], $caja['lat_max']])
            ->whereBetween('longitud', [$caja['lng_min'], $caja['lng_max']]);

        if (Geo::driverSoportaMatematicas($query->getConnection()->getDriverName())) {
            $expresion = Geo::expresionHaversine($lat, $lng, $unidad);

            $query->whereRaw("{$expresion} <= ?", [$radio])
                ->selectRaw("{$expresion} as distancia");
        }

        return $query;
    }

    /**
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeDelPropietario(Builder $query, Model $propietario): Builder
    {
        return $query->where('propietario_type', $propietario->getMorphClass())
            ->where('propietario_id', $propietario->getKey());
    }

    /**
     * Ordenamientos soportados por el catálogo.
     *
     * @param  Builder<Propiedad>  $query
     * @return Builder<Propiedad>
     */
    public function scopeOrdenar(Builder $query, ?string $orden): Builder
    {
        return match ($orden) {
            'precio_asc' => $query->orderBy('precio'),
            'precio_desc' => $query->orderByDesc('precio'),
            'area_desc' => $query->orderByDesc('area_total'),
            'vistas' => $query->orderByDesc('vistas_count'),
            'antiguas' => $query->orderBy('created_at'),
            'relevancia' => $query->orderByDesc('destacada')->orderByDesc('publicada_en'),
            default => $query->orderByDesc('publicada_en')->orderByDesc('created_at'),
        };
    }

    // ─────────────────────────────────────────────────────────────────────
    // Reglas del dominio
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Verifica si el usuario autenticado es dueño de la publicación.
     */
    public function esDelUsuario(User $user): bool
    {
        if (! $user->publicaPropiedades()) {
            return false;
        }

        $propietario = $user->propietario();

        if ($propietario === null) {
            return false;
        }

        return $this->propietario_type === $propietario->getMorphClass()
            && (int) $this->propietario_id === (int) $propietario->getKey();
    }

    public function estaPublicada(): bool
    {
        return $this->estado === EstadoPropiedad::Publicada;
    }

    public function esCerrada(): bool
    {
        return $this->estado->esCerrada();
    }

    /**
     * Requisitos mínimos para publicar. Devuelve la lista de faltantes.
     *
     * @return array<int, string>
     */
    public function requisitosFaltantesParaPublicar(): array
    {
        $faltantes = [];

        if (blank($this->titulo)) {
            $faltantes[] = 'titulo';
        }

        if ((float) $this->precio <= 0) {
            $faltantes[] = 'precio';
        }

        if (blank($this->direccion) || blank($this->ciudad)) {
            $faltantes[] = 'ubicacion';
        }

        $tieneFotos = $this->relationLoaded('fotos')
            ? $this->fotos->isNotEmpty()
            : $this->fotos()->exists();

        if (! $tieneFotos) {
            $faltantes[] = 'fotos';
        }

        return $faltantes;
    }

    public function puedePublicarse(): bool
    {
        return $this->requisitosFaltantesParaPublicar() === [];
    }

    /**
     * Precio con símbolo de moneda.
     */
    public function precioFormateado(): string
    {
        return $this->moneda->formatear($this->precio);
    }

    /**
     * Precio por metro cuadrado (útil en reportes).
     */
    public function precioPorMetro(): ?float
    {
        $area = (float) $this->area_total;

        return $area > 0 ? round((float) $this->precio / $area, 2) : null;
    }

    /**
     * Distancia en km desde un punto (cálculo en PHP, sin SQL).
     */
    public function distanciaDesde(float $lat, float $lng, string $unidad = 'km'): ?float
    {
        if ($this->latitud === null || $this->longitud === null) {
            return null;
        }

        return Geo::distancia($lat, $lng, (float) $this->latitud, (float) $this->longitud, $unidad);
    }

    /**
     * Registra una vista incrementando el contador desnormalizado.
     */
    public function registrarVista(): void
    {
        $this->increment('vistas_count');
    }

    /**
     * Indica si la publicación fue creada en los últimos N días.
     */
    public function esNueva(int $dias = 7): bool
    {
        $referencia = $this->publicada_en ?? $this->created_at;

        return $referencia instanceof Carbon && $referencia->greaterThan(now()->subDays($dias));
    }
}
