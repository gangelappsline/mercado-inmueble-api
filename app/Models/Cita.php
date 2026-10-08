<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoCita;
use App\Enums\TipoCita;
use Database\Factories\CitaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Cita de la agenda: visita presencial, visita virtual o llamada.
 * Los solapamientos se validan en CitaService antes de persistir.
 *
 * @property EstadoCita $estado
 * @property TipoCita $tipo
 */
class Cita extends Model
{
    /** @use HasFactory<CitaFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'citas';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'propiedad_id',
        'cliente_id',
        'interes_id',
        'propietario_type',
        'propietario_id',
        'fecha',
        'hora',
        'duracion_minutos',
        'tipo',
        'estado',
        'lugar',
        'enlace_virtual',
        'notas',
        'motivo_cancelacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'duracion_minutos' => 'integer',
            'tipo' => TipoCita::class,
            'estado' => EstadoCita::class,
            'confirmada_en' => 'datetime',
            'reprogramada_en' => 'datetime',
            'cancelada_en' => 'datetime',
            'completada_en' => 'datetime',
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
     * @return BelongsTo<Interes, $this>
     */
    public function interes(): BelongsTo
    {
        return $this->belongsTo(Interes::class);
    }

    /**
     * Anunciante con el que se agenda (inmobiliaria | vendedor).
     *
     * @return MorphTo<Model, $this>
     */
    public function propietario(): MorphTo
    {
        return $this->morphTo();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  Builder<Cita>  $query
     * @return Builder<Cita>
     */
    public function scopeProximas(Builder $query): Builder
    {
        return $query->where('fecha', '>=', now()->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora');
    }

    /**
     * @param  Builder<Cita>  $query
     * @return Builder<Cita>
     */
    public function scopeDeFecha(Builder $query, string $fecha): Builder
    {
        return $query->whereDate('fecha', $fecha)->orderBy('hora');
    }

    /**
     * @param  Builder<Cita>  $query
     * @return Builder<Cita>
     */
    public function scopeEnRangoDeFechas(Builder $query, string $desde, string $hasta): Builder
    {
        return $query->whereBetween('fecha', [$desde, $hasta]);
    }

    /**
     * @param  Builder<Cita>  $query
     * @return Builder<Cita>
     */
    public function scopePorEstado(Builder $query, EstadoCita|string|null $estado): Builder
    {
        $valor = $estado instanceof EstadoCita ? $estado->value : $estado;

        return $query->when(filled($valor), static fn (Builder $query) => $query->where('estado', $valor));
    }

    /**
     * @param  Builder<Cita>  $query
     * @return Builder<Cita>
     */
    public function scopeDelPropietario(Builder $query, Model $propietario): Builder
    {
        return $query->where('propietario_type', $propietario->getMorphClass())
            ->where('propietario_id', $propietario->getKey());
    }

    /**
     * @param  Builder<Cita>  $query
     * @return Builder<Cita>
     */
    public function scopeDelCliente(Builder $query, Cliente|int $cliente): Builder
    {
        return $query->where('cliente_id', $cliente instanceof Cliente ? $cliente->getKey() : $cliente);
    }

    /**
     * Citas que ocupan un espacio de la agenda en una fecha dada.
     *
     * @param  Builder<Cita>  $query
     * @return Builder<Cita>
     */
    public function scopeQueBloqueanAgenda(Builder $query): Builder
    {
        return $query->whereIn('estado', array_map(
            static fn (EstadoCita $estado): string => $estado->value,
            array_filter(EstadoCita::cases(), static fn (EstadoCita $estado): bool => $estado->bloqueaAgenda()),
        ));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fechas y solapamientos
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Momento de inicio de la cita.
     */
    public function inicio(): Carbon
    {
        $fecha = $this->fecha instanceof Carbon ? $this->fecha->copy() : Carbon::parse((string) $this->fecha);

        return $fecha->setTimeFromTimeString($this->hora ?: '00:00:00');
    }

    /**
     * Momento de finalización según la duración.
     */
    public function fin(): Carbon
    {
        return $this->inicio()->copy()->addMinutes((int) $this->duracion_minutos);
    }

    /**
     * Verifica si esta cita se solapa con otro rango horario.
     */
    public function solapaCon(Carbon|\DateTimeInterface $inicio, int $duracionMinutos): bool
    {
        $inicioPropuesto = $inicio instanceof Carbon ? $inicio->copy() : Carbon::parse($inicio);
        $finPropuesto = $inicioPropuesto->copy()->addMinutes($duracionMinutos);

        return $this->inicio()->lessThan($finPropuesto) && $this->fin()->greaterThan($inicioPropuesto);
    }

    /**
     * Verifica si la cita ya ocurrió.
     */
    public function estaEnElPasado(): bool
    {
        return $this->fin()->isPast();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Transiciones de estado
    // ─────────────────────────────────────────────────────────────────────

    public function puedeConfirmarse(): bool
    {
        return in_array($this->estado, [EstadoCita::Pendiente, EstadoCita::Reprogramada], true)
            && ! $this->estaEnElPasado();
    }

    public function puedeReprogramarse(): bool
    {
        return ! $this->estado->esFinal() && ! $this->estaEnElPasado();
    }

    public function puedeCancelarse(): bool
    {
        return ! $this->estado->esFinal();
    }

    /**
     * Confirma la cita. El cambio de estado lo orquesta CitaService.
     */
    public function confirmar(): void
    {
        $this->forceFill([
            'estado' => EstadoCita::Confirmada,
            'confirmada_en' => now(),
        ])->save();
    }

    /**
     * Reprograma la cita a una nueva fecha/hora conservando el motivo.
     */
    public function reprogramar(string $fecha, string $hora, ?string $motivo = null): void
    {
        $this->forceFill([
            'fecha' => $fecha,
            'hora' => $hora,
            'estado' => EstadoCita::Reprogramada,
            'reprogramada_en' => now(),
            'notas' => $motivo !== null ? trim((string) $this->notas."\n".$motivo) : $this->notas,
        ])->save();
    }

    public function cancelar(?string $motivo = null): void
    {
        $this->forceFill([
            'estado' => EstadoCita::Cancelada,
            'cancelada_en' => now(),
            'motivo_cancelacion' => $motivo,
        ])->save();
    }

    public function completar(): void
    {
        $this->forceFill([
            'estado' => EstadoCita::Completada,
            'completada_en' => now(),
        ])->save();
    }

    public function marcarNoAsistio(): void
    {
        $this->forceFill(['estado' => EstadoCita::NoAsistio])->save();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Presentación y permisos
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Rango horario legible: "14:30 – 15:00".
     */
    public function rangoHorario(): string
    {
        return sprintf('%s – %s', $this->inicio()->format('H:i'), $this->fin()->format('H:i'));
    }

    /**
     * Resumen para notificaciones y correos.
     */
    public function resumen(): string
    {
        return sprintf(
            '%s del %s a las %s (%s)',
            $this->tipo->label(),
            $this->inicio()->translatedFormat('d \d\e F \d\e Y'),
            $this->inicio()->format('H:i'),
            Str::limit((string) $this->propiedad?->titulo, 60) ?: 'propiedad',
        );
    }

    /**
     * Verifica si el usuario es participante (cliente o anunciante).
     */
    public function participa(User $user): bool
    {
        $propietario = $user->propietario();

        if ($propietario !== null
            && $this->propietario_type === $propietario->getMorphClass()
            && (int) $this->propietario_id === (int) $propietario->getKey()) {
            return true;
        }

        return (int) $this->cliente_id === (int) ($user->perfilCliente()?->getKey() ?? 0);
    }

    /**
     * Verifica si el usuario es el anunciante de la cita.
     */
    public function esDelPropietario(User $user): bool
    {
        $propietario = $user->propietario();

        return $propietario !== null
            && $this->propietario_type === $propietario->getMorphClass()
            && (int) $this->propietario_id === (int) $propietario->getKey();
    }
}
