<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContactoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mensaje recibido por el formulario público de contacto.
 */
class Contacto extends Model
{
    /** @use HasFactory<ContactoFactory> */
    use HasFactory;

    protected $table = 'contactos';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'email',
        'telefono',
        'asunto',
        'mensaje',
        'ip',
        'user_agent',
        'atendido',
        'atendido_en',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'atendido' => 'boolean',
            'atendido_en' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Contacto>  $query
     * @return Builder<Contacto>
     */
    public function scopeNoAtendidos(Builder $query): Builder
    {
        return $query->where('atendido', false);
    }

    /**
     * @param  Builder<Contacto>  $query
     * @return Builder<Contacto>
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->noAtendidos()->orderBy('created_at');
    }

    /**
     * Marca el mensaje como atendido.
     */
    public function marcarAtendido(): void
    {
        $this->forceFill(['atendido' => true, 'atendido_en' => now()])->save();
    }
}
