<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Video de la propiedad: máximo uno por propiedad (regla validada en
 * PropiedadService e impuesta por índice único en base de datos).
 */
class PropiedadVideo extends Model
{
    use HasFactory;

    protected $table = 'propiedad_videos';

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
        'duracion',
        'thumbnail',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tamanio' => 'integer',
            'duracion' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Propiedad, $this>
     */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /**
     * Indica si el video vive en un disco privado (requiere URL firmada).
     */
    public function esPrivado(): bool
    {
        return ! in_array($this->disk, ['public'], true);
    }

    /**
     * URL de reproducción: firmada y temporal cuando el disco es privado.
     */
    public function urlTemporal(): string
    {
        if (! $this->esPrivado()) {
            return Storage::disk($this->disk)->url($this->ruta);
        }

        $minutos = (int) config('mercado.media.url_firmada_minutos', 30);

        if ($this->disk === 's3') {
            return Storage::disk($this->disk)->temporaryUrl($this->ruta, Carbon::now()->addMinutes($minutos));
        }

        return URL::temporarySignedRoute('media.video', Carbon::now()->addMinutes($minutos), ['video' => $this->getKey()]);
    }

    /**
     * URL del poster del video.
     */
    public function urlThumbnail(): ?string
    {
        return $this->thumbnail !== null ? Storage::disk('public')->url($this->thumbnail) : null;
    }

    /**
     * Duración legible mm:ss.
     */
    public function duracionLegible(): ?string
    {
        if ($this->duracion === null) {
            return null;
        }

        return sprintf('%02d:%02d', intdiv((int) $this->duracion, 60), (int) $this->duracion % 60);
    }

    /**
     * Tamaño del video en megabytes.
     */
    public function pesoMb(): float
    {
        return round((int) $this->tamanio / 1048576, 2);
    }
}
