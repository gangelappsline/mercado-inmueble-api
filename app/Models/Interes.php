<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoInteres;
use App\Enums\Role;
use Database\Factories\InteresFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Interés de un cliente por una propiedad: punto de entrada del embudo
 * comercial. Al registrarse se crea el hilo de conversación y se notifica
 * al anunciante (ver InteresService).
 *
 * @property EstadoInteres $estado
 */
class Interes extends Model
{
    /** @use HasFactory<InteresFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'intereses';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'propiedad_id',
        'cliente_id',
        'mensaje',
        'estado',
        'origen',
        'contactado',
        'atendido_en',
        'cerrado_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoInteres::class,
            'contactado' => 'boolean',
            'atendido_en' => 'datetime',
            'cerrado_en' => 'datetime',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relaciones
    // ─────────────────────────────────────────────────────────────────────

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

    /**
     * Hilo de mensajes asociado al interés.
     *
     * @return HasOne<Hilo, $this>
     */
    public function hilo(): HasOne
    {
        return $this->hasOne(Hilo::class);
    }

    /**
     * @return HasMany<Cita, $this>
     */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  Builder<Interes>  $query
     * @return Builder<Interes>
     */
    public function scopePorEstado(Builder $query, EstadoInteres|string|null $estado): Builder
    {
        $valor = $estado instanceof EstadoInteres ? $estado->value : $estado;

        return $query->when(filled($valor), static fn (Builder $query) => $query->where('estado', $valor));
    }

    /**
     * @param  Builder<Interes>  $query
     * @return Builder<Interes>
     */
    public function scopeNuevos(Builder $query): Builder
    {
        return $query->where('estado', EstadoInteres::Nuevo->value);
    }

    /**
     * Intereses recibidos por un anunciante (a través de sus propiedades).
     *
     * @param  Builder<Interes>  $query
     * @return Builder<Interes>
     */
    public function scopeDelPropietario(Builder $query, Model $propietario): Builder
    {
        return $query->whereHas('propiedad', static fn (Builder $query) => $query
            ->where('propietario_type', $propietario->getMorphClass())
            ->where('propietario_id', $propietario->getKey()));
    }

    /**
     * @param  Builder<Interes>  $query
     * @return Builder<Interes>
     */
    public function scopeDelCliente(Builder $query, Cliente|int $cliente): Builder
    {
        return $query->where('cliente_id', $cliente instanceof Cliente ? $cliente->getKey() : $cliente);
    }

    /**
     * @param  Builder<Interes>  $query
     * @return Builder<Interes>
     */
    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Comportamiento
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Marca el interés como contactado por el anunciante.
     */
    public function marcarContactado(): void
    {
        $this->forceFill(['contactado' => true])->save();
    }

    /**
     * Marca el interés como atendido (ya hubo respuesta).
     */
    public function marcarAtendido(): void
    {
        $this->forceFill([
            'contactado' => true,
            'estado' => EstadoInteres::Atendido,
            'atendido_en' => $this->atendido_en ?? now(),
        ])->save();
    }

    /**
     * Cierra el interés como conversión.
     */
    public function cerrar(): void
    {
        $this->forceFill([
            'estado' => EstadoInteres::Cerrado,
            'cerrado_en' => now(),
        ])->save();
    }

    /**
     * Descarta el interés (no calificado).
     */
    public function descartar(): void
    {
        $this->forceFill([
            'estado' => EstadoInteres::Descartado,
            'cerrado_en' => now(),
        ])->save();
    }

    public function esFinal(): bool
    {
        return $this->estado->esFinal();
    }

    /**
     * Minutos transcurridos desde que se registró el interés.
     */
    public function antiguedadMinutos(): int
    {
        return (int) $this->created_at?->diffInMinutes(now());
    }

    /**
     * Verifica si el usuario participa del interés (cliente o anunciante).
     */
    public function participa(User $user): bool
    {
        if ($user->hasRole(Role::Cliente)) {
            return (int) $this->cliente_id === (int) $user->perfilCliente()?->getKey();
        }

        $propietario = $user->propietario();

        return $propietario !== null
            && $this->propiedad !== null
            && $this->propiedad->propietario_type === $propietario->getMorphClass()
            && (int) $this->propiedad->propietario_id === (int) $propietario->getKey();
    }
}
