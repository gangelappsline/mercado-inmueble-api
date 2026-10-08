<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\PropietarioDePropiedades;
use App\Models\Concerns\Auditable;
use Database\Factories\VendedorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Perfil extendido de un vendedor particular (persona natural).
 */
class Vendedor extends Model implements PropietarioDePropiedades
{
    /** @use HasFactory<VendedorFactory> */
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'vendedores';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'nombres',
        'apellidos',
        'dni',
        'telefono',
        'telefono_alternativo',
        'direccion',
        'ciudad',
        'estado_provincia',
        'fecha_nacimiento',
        'biografia',
        'foto',
        'verificado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'verificado' => 'boolean',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function atributosAuditables(): array
    {
        return ['nombres', 'apellidos', 'dni', 'telefono', 'ciudad', 'verificado'];
    }

    /**
     * Atributos derivados incluidos al serializar.
     *
     * @var array<int, string>
     */
    protected $appends = ['nombre_completo'];

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
     * @return HasManyThrough<Interes, Propiedad, $this>
     */
    public function intereses(): HasManyThrough
    {
        return $this->hasManyThrough(Interes::class, Propiedad::class, 'propietario_id', 'propiedad_id')
            ->where('propiedades.propietario_type', (new Propiedad)->getMorphClass());
    }

    // ─────────────────────────────────────────────────────────────────────
    // Atributos y consultas
    // ─────────────────────────────────────────────────────────────────────

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    /**
     * Iniciales para avatares en el frontend.
     */
    public function iniciales(): string
    {
        return Str::upper(Str::substr($this->nombres, 0, 1).Str::substr($this->apellidos, 0, 1));
    }

    /**
     * @param  Builder<Vendedor>  $query
     * @return Builder<Vendedor>
     */
    public function scopeVerificados(Builder $query): Builder
    {
        return $query->where('verificado', true);
    }

    /**
     * @param  Builder<Vendedor>  $query
     * @return Builder<Vendedor>
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        return $query->when(filled($termino), static function (Builder $query) use ($termino): void {
            $comodin = '%'.$termino.'%';

            $query->where(static function (Builder $query) use ($comodin): void {
                $query->where('nombres', 'like', $comodin)
                    ->orWhere('apellidos', 'like', $comodin)
                    ->orWhere('dni', 'like', $comodin)
                    ->orWhere('ciudad', 'like', $comodin);
            });
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Contrato PropietarioDePropiedades
    // ─────────────────────────────────────────────────────────────────────

    public function nombreComercial(): string
    {
        return $this->nombre_completo;
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
        return $this->foto !== null ? Storage::disk('public')->url($this->foto) : null;
    }
}
