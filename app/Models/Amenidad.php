<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoriaAmenidad;
use Database\Factories\AmenidadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Catálogo de amenidades. Se cachea porque se consulta en cada listado público
 * y en el formulario de publicación.
 */
class Amenidad extends Model
{
    /** @use HasFactory<AmenidadFactory> */
    use HasFactory;

    /**
     * Clave de caché del catálogo completo.
     */
    public const CACHE_KEY = 'catalogo.amenidades';

    protected $table = 'amenidades';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'slug',
        'icono',
        'categoria',
        'activa',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'categoria' => CategoriaAmenidad::class,
            'activa' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /**
     * Genera el slug del catálogo y limpia la caché al guardar.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $amenidad): void {
            $amenidad->slug ??= Str::slug($amenidad->nombre);
        });

        static::saved(static function (): void {
            self::limpiarCache();
        });

        static::deleted(static function (): void {
            self::limpiarCache();
        });
    }

    /**
     * Propiedades que ofrecen esta amenidad.
     *
     * @return BelongsToMany<Propiedad, $this>
     */
    public function propiedades(): BelongsToMany
    {
        return $this->belongsToMany(Propiedad::class, 'amenidad_propiedad')->withTimestamps();
    }

    /**
     * @param  Builder<Amenidad>  $query
     * @return Builder<Amenidad>
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    /**
     * @param  Builder<Amenidad>  $query
     * @return Builder<Amenidad>
     */
    public function scopeDeCategoria(Builder $query, CategoriaAmenidad|string $categoria): Builder
    {
        return $query->where('categoria', $categoria instanceof CategoriaAmenidad ? $categoria->value : $categoria);
    }

    /**
     * Catálogo activo agrupado por categoría, servido desde caché.
     *
     * @return Collection<int, Amenidad>
     */
    public static function catalogo(): Collection
    {
        return Cache::remember(
            self::CACHE_KEY,
            (int) config('mercado.catalogo_cache_segundos', 3600),
            static fn (): Collection => self::query()
                ->activas()
                ->orderBy('categoria')
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(),
        );
    }

    /**
     * Invalida la caché del catálogo.
     */
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
