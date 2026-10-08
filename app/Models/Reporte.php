<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoReporte;
use Database\Factories\ReporteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Agregado diario de métricas por propiedad y tipo (vistas, contactos,
 * favoritos, citas y conversiones). ReporteService hace upsert atómico aquí.
 *
 * @property TipoReporte $tipo
 */
class Reporte extends Model
{
    /** @use HasFactory<ReporteFactory> */
    use HasFactory;

    protected $table = 'reportes';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'propietario_type',
        'propietario_id',
        'propiedad_id',
        'cliente_id',
        'tipo',
        'fecha',
        'cantidad',
        'valor',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoReporte::class,
            'fecha' => 'date',
            'cantidad' => 'integer',
            'valor' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relaciones
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return MorphTo<Model, $this>
     */
    public function propietario(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Propiedad, $this>
     */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  Builder<Reporte>  $query
     * @return Builder<Reporte>
     */
    public function scopePorTipo(Builder $query, TipoReporte|string $tipo): Builder
    {
        return $query->where('tipo', $tipo instanceof TipoReporte ? $tipo->value : $tipo);
    }

    /**
     * @param  Builder<Reporte>  $query
     * @return Builder<Reporte>
     */
    public function scopeEnRango(Builder $query, string $desde, string $hasta): Builder
    {
        return $query->whereBetween('fecha', [$desde, $hasta]);
    }

    /**
     * @param  Builder<Reporte>  $query
     * @return Builder<Reporte>
     */
    public function scopeDelPropietario(Builder $query, Model $propietario): Builder
    {
        return $query->where('propietario_type', $propietario->getMorphClass())
            ->where('propietario_id', $propietario->getKey());
    }

    /**
     * @param  Builder<Reporte>  $query
     * @return Builder<Reporte>
     */
    public function scopeDePropiedades(Builder $query, array $propiedadIds): Builder
    {
        return $query->whereIn('propiedad_id', $propiedadIds);
    }

    /**
     * @param  Builder<Reporte>  $query
     * @return Builder<Reporte>
     */
    public function scopeUltimosDias(Builder $query, int $dias): Builder
    {
        return $query->where('fecha', '>=', now()->subDays($dias)->toDateString());
    }

    /**
     * Descripción legible de la métrica.
     */
    public function descripcion(): string
    {
        return sprintf(
            '%s · %s · %d',
            $this->tipo->label(),
            $this->fecha?->format('d/m/Y'),
            (int) $this->cantidad,
        );
    }
}
