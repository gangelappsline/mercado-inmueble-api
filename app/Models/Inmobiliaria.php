<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\PropietarioDePropiedades;
use App\Models\Concerns\Auditable;
use Database\Factories\InmobiliariaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Perfil extendido de una inmobiliaria: razón social, RUC/NIT, logo y datos de
 * contacto público.
 */
class Inmobiliaria extends Model implements PropietarioDePropiedades
{
    /** @use HasFactory<InmobiliariaFactory> */
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'inmobiliarias';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'razon_social',
        'nombre_comercial',
        'ruc',
        'logo',
        'direccion',
        'telefono',
        'telefono_alternativo',
        'web',
        'descripcion',
        'ciudad',
        'estado_provincia',
        'pais',
        'verificado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['verificado' => 'boolean'];
    }

    /**
     * @return array<int, string>
     */
    protected function atributosAuditables(): array
    {
        return ['razon_social', 'nombre_comercial', 'ruc', 'logo', 'telefono', 'ciudad', 'verificado'];
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
     * @return MorphMany<Propiedad, $this>
     */
    public function propiedades(): MorphMany
    {
        return $this->morphMany(Propiedad::class, 'propietario');
    }

    /**
     * @return MorphMany<Cita, $this>
     */
    public function citas(): MorphMany
    {
        return $this->morphMany(Cita::class, 'propietario');
    }

    /**
     * @return MorphMany<Reporte, $this>
     */
    public function reportes(): MorphMany
    {
        return $this->morphMany(Reporte::class, 'propietario');
    }

    /**
     * Intereses recibidos en todas sus propiedades.
     *
     * @return HasManyThrough<Interes, Propiedad, $this>
     */
    public function intereses(): HasManyThrough
    {
        return $this->hasManyThrough(Interes::class, Propiedad::class, 'propietario_id', 'propiedad_id')
            ->where('propiedades.propietario_type', (new Propiedad)->getMorphClass());
    }

    // ─────────────────────────────────────────────────────────────────────
    // Consultas
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  Builder<Inmobiliaria>  $query
     * @return Builder<Inmobiliaria>
     */
    public function scopeVerificadas(Builder $query): Builder
    {
        return $query->where('verificado', true);
    }

    /**
     * Búsqueda por razón social, nombre comercial, RUC o ciudad.
     *
     * @param  Builder<Inmobiliaria>  $query
     * @return Builder<Inmobiliaria>
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when(filled($termino), static function (Builder $query) use ($termino): void {
            $comodin = '%'.$termino.'%';

            $query->where(static function (Builder $query) use ($comodin): void {
                $query->where('razon_social', 'like', $comodin)
                    ->orWhere('nombre_comercial', 'like', $comodin)
                    ->orWhere('ruc', 'like', $comodin)
                    ->orWhere('ciudad', 'like', $comodin);
            });
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Contrato PropietarioDePropiedades
    // ─────────────────────────────────────────────────────────────────────

    public function nombreComercial(): string
    {
        return (string) ($this->nombre_comercial ?: $this->razon_social);
    }

    public function emailContacto(): string
    {
        return (string) $this->user->email;
    }

    public function telefonoContacto(): ?string
    {
        return $this->telefono ?: $this->user?->phone;
    }

    public function urlLogo(): ?string
    {
        return $this->logo !== null ? Storage::disk('public')->url($this->logo) : null;
    }
}
