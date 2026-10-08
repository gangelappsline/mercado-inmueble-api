<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Moneda;
use App\Enums\TipoPropiedad;
use App\Models\Concerns\Auditable;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Perfil extendido del cliente: presupuesto y preferencias de búsqueda usados
 * para personalizar el catálogo (`/api/v1/cliente/propiedades`).
 */
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'clientes';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'telefono',
        'telefono_alternativo',
        'presupuesto_min',
        'presupuesto_max',
        'moneda',
        'tipo_propiedad_interes',
        'ciudad_interes',
        'habitaciones_min',
        'preferencias',
        'acepta_terminos',
        'recibe_novedades',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'presupuesto_min' => 'decimal:2',
            'presupuesto_max' => 'decimal:2',
            'moneda' => Moneda::class,
            'tipo_propiedad_interes' => TipoPropiedad::class,
            'habitaciones_min' => 'integer',
            'preferencias' => 'array',
            'acepta_terminos' => 'boolean',
            'recibe_novedades' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function atributosAuditables(): array
    {
        return ['telefono', 'presupuesto_min', 'presupuesto_max', 'ciudad_interes', 'habitaciones_min'];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relaciones
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Favorito, $this>
     */
    public function favoritos(): HasMany
    {
        return $this->hasMany(Favorito::class);
    }

    /**
     * Propiedades guardadas como favoritas.
     *
     * @return BelongsToMany<Propiedad, $this>
     */
    public function propiedadesFavoritas(): BelongsToMany
    {
        return $this->belongsToMany(Propiedad::class, 'favoritos')
            ->withPivot(['id', 'nota'])
            ->withTimestamps();
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
     * Hilos de conversación abiertos por el cliente.
     *
     * @return HasManyThrough<Hilo, Interes, $this>
     */
    public function hilos(): HasManyThrough
    {
        return $this->hasManyThrough(Hilo::class, Interes::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Comportamiento
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  Builder<Cliente>  $query
     * @return Builder<Cliente>
     */
    public function scopeEnCiudad(Builder $query, ?string $ciudad): Builder
    {
        return $query->when(filled($ciudad), fn (Builder $query) => $query->where('ciudad_interes', $ciudad));
    }

    /**
     * Nombre del cliente (vive en la tabla users).
     */
    public function nombreCompleto(): string
    {
        return (string) ($this->user?->name ?? '');
    }

    /**
     * Verifica si la propiedad ya está en favoritos.
     */
    public function tieneFavorito(Propiedad $propiedad): bool
    {
        return $this->favoritos()->where('propiedad_id', $propiedad->id)->exists();
    }

    /**
     * Rango de presupuesto legible: "USD 50.000 – 120.000".
     */
    public function rangoPresupuesto(): ?string
    {
        if ($this->presupuesto_min === null && $this->presupuesto_max === null) {
            return null;
        }

        $moneda = $this->moneda ?? Moneda::porDefecto();

        return sprintf(
            '%s %s – %s',
            $moneda->value,
            number_format((float) ($this->presupuesto_min ?? 0), 0, ',', '.'),
            number_format((float) ($this->presupuesto_max ?? 0), 0, ',', '.'),
        );
    }

    /**
     * Filtros guardados del cliente (usados por la búsqueda personalizada).
     *
     * @return array<string, mixed>
     */
    public function preferenciasDeBusqueda(): array
    {
        return array_filter([
            'tipo' => $this->tipo_propiedad_interes?->value,
            'ciudad' => $this->ciudad_interes,
            'precio_min' => $this->presupuesto_min,
            'precio_max' => $this->presupuesto_max,
            'moneda' => $this->moneda?->value,
            'habitaciones_min' => $this->habitaciones_min,
        ], static fn (mixed $valor): bool => $valor !== null && $valor !== '');
    }
}
