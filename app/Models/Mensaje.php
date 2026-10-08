<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Database\Factories\MensajeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mensaje dentro de un hilo de conversación.
 *
 * @property Role $rol_autor
 */
class Mensaje extends Model
{
    /** @use HasFactory<MensajeFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'mensajes';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'hilo_id',
        'user_id',
        'rol_autor',
        'cuerpo',
        'adjunto',
        'leido',
        'leido_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rol_autor' => Role::class,
            'leido' => 'boolean',
            'leido_en' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Hilo, $this>
     */
    public function hilo(): BelongsTo
    {
        return $this->belongsTo(Hilo::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  Builder<Mensaje>  $query
     * @return Builder<Mensaje>
     */
    public function scopeNoLeidos(Builder $query): Builder
    {
        return $query->where('leido', false);
    }

    /**
     * @param  Builder<Mensaje>  $query
     * @return Builder<Mensaje>
     */
    public function scopeRecientes(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * Marca el mensaje como leído.
     */
    public function marcarLeido(): void
    {
        if ($this->leido) {
            return;
        }

        $this->forceFill(['leido' => true, 'leido_en' => now()])->save();
    }

    public function esDelCliente(): bool
    {
        return $this->rol_autor === Role::Cliente;
    }

    /**
     * Extracto del mensaje para las listas.
     */
    public function extracto(int $largo = 120): string
    {
        return str((string) $this->cuerpo)->squish()->limit($largo)->value();
    }
}
