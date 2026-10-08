<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Database\Factories\HiloFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Hilo de conversación interes ↔ inmobiliaria/vendedor. Los participantes se
 * derivan del interés (cliente) y de la propiedad (anunciante).
 */
class Hilo extends Model
{
    /** @use HasFactory<HiloFactory> */
    use HasFactory;

    protected $table = 'hilos';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'interes_id',
        'asunto',
        'ultimo_mensaje_en',
        'no_leidos_cliente',
        'no_leidos_propietario',
        'total_mensajes',
        'cerrado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ultimo_mensaje_en' => 'datetime',
            'no_leidos_cliente' => 'integer',
            'no_leidos_propietario' => 'integer',
            'total_mensajes' => 'integer',
            'cerrado' => 'boolean',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relaciones
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return BelongsTo<Interes, $this>
     */
    public function interes(): BelongsTo
    {
        return $this->belongsTo(Interes::class);
    }

    /**
     * Mensajes del hilo en orden cronológico.
     *
     * @return HasMany<Mensaje, $this>
     */
    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Último mensaje publicado (para la bandeja de entrada).
     *
     * @return HasOne<Mensaje, $this>
     */
    public function ultimoMensaje(): HasOne
    {
        return $this->hasOne(Mensaje::class)->latestOfMany();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Participantes
    // ─────────────────────────────────────────────────────────────────────

    public function cliente(): ?Cliente
    {
        return $this->interes?->cliente;
    }

    public function propietario(): Inmobiliaria|Vendedor|null
    {
        $propietario = $this->interes?->propiedad?->propietario;

        return $propietario instanceof Inmobiliaria || $propietario instanceof Vendedor ? $propietario : null;
    }

    public function clienteUser(): ?User
    {
        return $this->cliente()?->user;
    }

    public function propietarioUser(): ?User
    {
        return $this->propietario()?->user;
    }

    /**
     * Verifica si el usuario es participante del hilo.
     */
    public function participa(User $user): bool
    {
        $propietario = $user->propietario();

        if ($propietario !== null && $this->propietario()?->is($propietario)) {
            return true;
        }

        return (int) ($this->clienteUser()?->getKey() ?? 0) === (int) $user->getKey();
    }

    /**
     * Verifica si el usuario es el anunciante (dueño de la propiedad).
     */
    public function esPropietario(User $user): bool
    {
        $propietario = $user->propietario();

        return $propietario !== null && $this->propietario()?->is($propietario);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Hilos donde participa un anunciante.
     *
     * @param  Builder<Hilo>  $query
     * @return Builder<Hilo>
     */
    public function scopeDelPropietario(Builder $query, Model $propietario): Builder
    {
        return $query->whereHas('interes.propiedad', static fn (Builder $query) => $query
            ->where('propietario_type', $propietario->getMorphClass())
            ->where('propietario_id', $propietario->getKey()));
    }

    /**
     * @param  Builder<Hilo>  $query
     * @return Builder<Hilo>
     */
    public function scopeDelCliente(Builder $query, Cliente|int $cliente): Builder
    {
        return $query->whereHas('interes', static fn (Builder $query) => $query
            ->where('cliente_id', $cliente instanceof Cliente ? $cliente->getKey() : $cliente));
    }

    /**
     * @param  Builder<Hilo>  $query
     * @return Builder<Hilo>
     */
    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->where('cerrado', false);
    }

    /**
     * @param  Builder<Hilo>  $query
     * @return Builder<Hilo>
     */
    public function scopeConNoLeidos(Builder $query, Role|string $rol): Builder
    {
        $columna = ($rol instanceof Role ? $rol->value : $rol) === Role::Cliente->value
            ? 'no_leidos_cliente'
            : 'no_leidos_propietario';

        return $query->where($columna, '>', 0);
    }

    /**
     * @param  Builder<Hilo>  $query
     * @return Builder<Hilo>
     */
    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('ultimo_mensaje_en')->orderByDesc('id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Comportamiento
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Actualiza los contadores tras publicar un mensaje.
     */
    public function registrarMensaje(Role $rolAutor): void
    {
        $this->forceFill([
            'ultimo_mensaje_en' => now(),
            'total_mensajes' => (int) $this->total_mensajes + 1,
            'no_leidos_cliente' => $rolAutor === Role::Cliente
                ? (int) $this->no_leidos_cliente
                : (int) $this->no_leidos_cliente + 1,
            'no_leidos_propietario' => $rolAutor === Role::Cliente
                ? (int) $this->no_leidos_propietario + 1
                : (int) $this->no_leidos_propietario,
        ])->save();
    }

    /**
     * Marca como leídos los mensajes del hilo para un rol.
     */
    public function marcarLeidoPor(Role $rol): void
    {
        $columna = $rol === Role::Cliente ? 'no_leidos_cliente' : 'no_leidos_propietario';

        if ((int) $this->{$columna} === 0) {
            return;
        }

        $this->forceFill([$columna => 0])->save();

        $this->mensajes()
            ->where('rol_autor', '!=', $rol->value)
            ->where('leido', false)
            ->update(['leido' => true, 'leido_en' => now()]);
    }

    /**
     * Cierra el hilo: no admite mensajes nuevos.
     */
    public function cerrar(): void
    {
        $this->forceFill(['cerrado' => true])->save();
    }

    /**
     * Reabre un hilo cerrado.
     */
    public function reabrir(): void
    {
        $this->forceFill(['cerrado' => false])->save();
    }

    /**
     * Asunto por defecto a partir de la propiedad.
     */
    public static function asuntoPara(Propiedad $propiedad): string
    {
        return sprintf('Consulta sobre %s (%s)', $propiedad->titulo, $propiedad->codigo);
    }
}
