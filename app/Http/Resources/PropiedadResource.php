<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Inmobiliaria;
use App\Models\Vendedor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tarjeta de propiedad para listados (catálogo público y panel).
 *
 * @mixin \App\Models\Propiedad
 */
class PropiedadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'slug' => $this->slug,
            'titulo' => $this->titulo,
            'tipo' => [
                'value' => $this->tipo->value,
                'label' => $this->tipo->label(),
            ],
            'operacion' => [
                'value' => $this->operacion->value,
                'label' => $this->operacion->label(),
            ],
            'estado' => [
                'value' => $this->estado->value,
                'label' => $this->estado->label(),
            ],
            'destacada' => (bool) $this->destacada,
            'precio' => (float) $this->precio,
            'precio_formateado' => $this->precioFormateado(),
            'precio_por_metro' => $this->precioPorMetro(),
            'moneda' => $this->moneda->value,
            'expensas' => $this->expensas !== null ? (float) $this->expensas : null,
            'precio_negociable' => (bool) $this->precio_negociable,
            'area_total' => (float) $this->area_total,
            'area_construida' => $this->area_construida !== null ? (float) $this->area_construida : null,
            'habitaciones' => $this->habitaciones,
            'banos' => $this->banos,
            'estacionamientos' => $this->estacionamientos,
            'amoblado' => (bool) $this->amoblado,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'estado_provincia' => $this->estado_provincia,
            'pais' => $this->pais,
            'latitud' => $this->latitud !== null ? (float) $this->latitud : null,
            'longitud' => $this->longitud !== null ? (float) $this->longitud : null,
            'foto_principal' => $this->fotoPrincipalResource($request),
            'fotos_count' => $this->whenCounted('fotos'),
            'tiene_video' => $this->relationLoaded('video') ? $this->video !== null : null,
            'vistas_count' => $this->vistas_count,
            'contactos_count' => $this->contactos_count,
            'favoritos_count' => $this->favoritos_count,
            'citas_count' => $this->citas_count,
            'es_nueva' => $this->esNueva(),
            'publicada_en' => $this->publicada_en?->toIso8601String(),
            'propietario' => $this->propietarioResumen($request),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Foto principal (o la primera disponible) cuando la galería está cargada.
     *
     * @return array<string, mixed>|null
     */
    protected function fotoPrincipalResource(Request $request): ?array
    {
        if (! $this->relationLoaded('fotos')) {
            return null;
        }

        $foto = $this->fotos->firstWhere('es_principal', true) ?? $this->fotos->first();

        return $foto !== null ? (new PropiedadFotoResource($foto))->resolve($request) : null;
    }

    /**
     * Resumen del anunciante (morph map: inmobiliaria|vendedor).
     *
     * @return array<string, mixed>|null
     */
    protected function propietarioResumen(Request $request): ?array
    {
        if (! $this->relationLoaded('propietario') || $this->propietario === null) {
            return null;
        }

        return match (true) {
            $this->propietario instanceof Inmobiliaria => [
                'tipo' => 'inmobiliaria',
                'id' => $this->propietario->id,
                'nombre' => $this->propietario->nombreComercial(),
                'logo_url' => $this->propietario->urlLogo(),
                'verificado' => (bool) $this->propietario->verificado,
                'ciudad' => $this->propietario->ciudad,
            ],
            $this->propietario instanceof Vendedor => [
                'tipo' => 'vendedor',
                'id' => $this->propietario->id,
                'nombre' => $this->propietario->nombreCompleto(),
                'foto_url' => $this->propietario->urlLogo(),
                'verificado' => (bool) $this->propietario->verificado,
                'ciudad' => $this->propietario->ciudad,
            ],
            default => null,
        };
    }
}
