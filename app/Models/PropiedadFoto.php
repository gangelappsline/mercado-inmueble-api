<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Foto de una propiedad. La foto principal (`es_principal`) se gestiona desde
 * PropiedadService para garantizar que exista una sola por propiedad.
 *
 * @property-read string $url
 * @property-read string|null $url_thumbnail
 */
class PropiedadFoto extends Model
{
    use HasFactory;

    protected $table = 'propiedad_fotos';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'propiedad_id',
        'ruta',
        'disk',
        'nombre_original',
        'mime',
        'tamanio',
        'ancho',
        'alto',
        'thumbnail',
        'orden',
        'es_principal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tamanio' => 'integer',
            'ancho' => 'integer',
            'alto' => 'integer',
            'orden' => 'integer',
            'es_principal' => 'boolean',
        ];
    }

    /**
     * @var array<int, string>
     */
    protected $appends = ['url', 'url_thumbnail'];

    /**
     * @return BelongsTo<Propiedad, $this>
     */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /**
     * URL pública de la foto.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->ruta);
    }

    /**
     * URL del thumbnail generado en la subida.
     */
    public function getUrlThumbnailAttribute(): ?string
    {
        return $this->thumbnail !== null ? Storage::disk($this->disk)->url($this->thumbnail) : null;
    }

    /**
     * Peso de la foto en kilobytes.
     */
    public function pesoKb(): float
    {
        return round((int) $this->tamanio / 1024, 2);
    }
}
