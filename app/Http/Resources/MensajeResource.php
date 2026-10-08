<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Mensaje de un hilo, con el adjunto servido por URL firmada cuando vive en el
 * disk privado.
 *
 * @mixin \App\Models\Mensaje
 */
class MensajeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cuerpo' => $this->cuerpo,
            'extracto' => $this->extracto(140),
            'rol_autor' => $this->rol_autor->value,
            'es_del_cliente' => $this->esDelCliente(),
            'adjunto' => $this->adjunto !== null ? basename((string) $this->adjunto) : null,
            'adjunto_url' => $this->urlDelAdjunto(),
            'leido' => (bool) $this->leido,
            'leido_en' => $this->leido_en?->toIso8601String(),
            'autor' => new UserResource($this->whenLoaded('autor')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * URL temporal firmada del adjunto (disk privado) o URL pública.
     */
    private function urlDelAdjunto(): ?string
    {
        if (blank($this->adjunto)) {
            return null;
        }

        $disk = (string) config('mercado.media.disco_videos');

        if ($disk === 'public') {
            return Storage::disk($disk)->url((string) $this->adjunto);
        }

        return URL::temporarySignedRoute(
            'media.adjunto',
            now()->addMinutes((int) config('mercado.media.url_firmada_minutos', 30)),
            ['mensaje' => $this->getKey()],
        );
    }
}
