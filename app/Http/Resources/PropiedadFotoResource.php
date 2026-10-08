<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Foto de la galería con su miniatura y peso.
 *
 * @mixin \App\Models\PropiedadFoto
 */
class PropiedadFotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'url_thumbnail' => $this->url_thumbnail,
            'nombre_original' => $this->nombre_original,
            'mime' => $this->mime,
            'peso_kb' => $this->pesoKb(),
            'ancho' => $this->ancho,
            'alto' => $this->alto,
            'orden' => $this->orden,
            'es_principal' => (bool) $this->es_principal,
        ];
    }
}
