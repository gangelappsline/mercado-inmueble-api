<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FavoritoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Propiedad guardada por un cliente.
 */
class Favorito extends Model
{
    /** @use HasFactory<FavoritoFactory> */
    use HasFactory;

    protected $table = 'favoritos';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'cliente_id',
        'propiedad_id',
        'nota',
    ];

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<Propiedad, $this>
     */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /**
     * @param  Builder<Favorito>  $query
     * @return Builder<Favorito>
     */
    public function scopeDelCliente(Builder $query, Cliente|int $cliente): Builder
    {
        return $query->where('cliente_id', $cliente instanceof Cliente ? $cliente->getKey() : $cliente);
    }
}
