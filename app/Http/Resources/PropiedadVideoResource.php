<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Video único de la propiedad. Si vive en un disk privado se expone una URL
 * temporal firmada (`media.video`, ver PropiedadVideo::urlTemporal()).
 *
 * @mixin \App\Models\PropiedadVideo
 */
class PropiedadVideoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->urlTemporal(),
            'url_thumbnail' => $this->urlThumbnail(),
            'nombre_original' => $this->nombre_original,
            'mime' => $this->mime,
            'peso_mb' => $this->pesoMb(),
            'duracion_segundos' => $this->duracion,
            'duracion_legible' => $this->duracionLegible(),
            'privado' => $this->esPrivado(),
        ];
    }
}
