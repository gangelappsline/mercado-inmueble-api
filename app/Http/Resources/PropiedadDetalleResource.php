<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Detalle completo de la propiedad: descripción, galería, video, amenidades,
 * datos de contacto del anunciante y propiedades similares.
 *
 * @mixin \App\Models\Propiedad
 */
class PropiedadDetalleResource extends PropiedadResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'descripcion' => $this->descripcion,
            'piso' => $this->piso,
            'anio_construccion' => $this->anio_construccion,
            'codigo_postal' => $this->codigo_postal,
            'amenidades' => AmenidadResource::collection($this->whenLoaded('amenidades')),
            'fotos' => PropiedadFotoResource::collection($this->whenLoaded('fotos')),
            'video' => $this->relationLoaded('video') && $this->video !== null
                ? (new PropiedadVideoResource($this->video))->resolve($request)
                : null,
            'intereses_count' => $this->whenCounted('intereses'),
            'similares' => static::collection($this->whenLoaded('similares')),
            'es_favorito' => $this->when(
                isset($this->es_favorito),
                (bool) $this->es_favorito,
            ),
            'contacto' => $this->contactoDelAnunciante(),
        ]);
    }

    /**
     * Datos de contacto del anunciante (sólo en el detalle público/administrativo).
     *
     * @return array<string, string|null>|null
     */
    private function contactoDelAnunciante(): ?array
    {
        $propietario = $this->relationLoaded('propietario') ? $this->propietario : null;

        if ($propietario === null) {
            return null;
        }

        /** @var \App\Contracts\PropietarioDePropiedades $propietario */
        return [
            'nombre' => $propietario->nombreComercial(),
            'email' => $propietario->emailContacto(),
            'telefono' => $propietario->telefonoContacto(),
            'url_logo' => $propietario->urlLogo(),
        ];
    }
}
