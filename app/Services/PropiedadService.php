<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoPropiedad;
use App\Enums\TipoReporte;
use App\Exceptions\BusinessException;
use App\Models\Cliente;
use App\Models\Propiedad;
use App\Models\PropiedadFoto;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Casos de uso de la propiedad: alta con fotos/video, edición, publicación y
 * métricas. Todas las operaciones que tocan más de una tabla van en
 * transacción para no dejar estados intermedios.
 */
final class PropiedadService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly ReporteService $reportes,
        private readonly NotificacionService $notificaciones,
        private readonly PropiedadRepositoryInterface $propiedades,
    ) {
    }

    /**
     * Crea una propiedad del propietario indicado (inmobiliaria|vendedor).
     *
     * @param  array<string, mixed>  $datos
     * @param  array<int, UploadedFile>  $fotos
     */
    public function crear(Model $propietario, array $datos, array $fotos = [], ?UploadedFile $video = null): Propiedad
    {
        $amenidades = $datos['amenidades'] ?? [];
        unset($datos['amenidades'], $datos['fotos'], $datos['video']);

        return DB::transaction(function () use ($propietario, $datos, $fotos, $video, $amenidades): Propiedad {
            $propiedad = new Propiedad($datos);
            $propiedad->propietario()->associate($propietario);
            $propiedad->estado = EstadoPropiedad::Borrador;
            $propiedad->save();

            if ($amenidades !== []) {
                $this->sincronizarAmenidades($propiedad, $amenidades);
            }

            foreach (array_values($fotos) as $indice => $foto) {
                $this->media->subirFotoPropiedad($propiedad, $foto, $indice === 0);
            }

            if ($video !== null) {
                $this->media->subirVideoPropiedad($propiedad, $video);
            }

            $this->auditar($propiedad, 'creada');

            return $this->propiedades->detalle($propiedad);
        });
    }

    /**
     * Actualiza los datos de una propiedad (no toca fotos ni video).
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Propiedad $propiedad, array $datos): Propiedad
    {
        $amenidades = $datos['amenidades'] ?? null;
        unset($datos['amenidades']);

        return DB::transaction(function () use ($propiedad, $datos, $amenidades): Propiedad {
            $propiedad->fill($datos);
            $propiedad->save();

            if (is_array($amenidades)) {
                $this->sincronizarAmenidades($propiedad, $amenidades);
            }

            $this->auditar($propiedad, 'actualizada');

            return $this->propiedades->detalle($propiedad);
        });
    }

    /**
     * Elimina la propiedad y sus archivos asociados (borrado lógico).
     */
    public function eliminar(Propiedad $propiedad): void
    {
        DB::transaction(function () use ($propiedad): void {
            // Los archivos SÍ se eliminan del storage: no tiene sentido
            // conservar medios huérfanos de una publicación borrada.
            $propiedad->loadMissing(['fotos', 'video']);

            foreach ($propiedad->fotos as $foto) {
                $this->media->eliminarFoto($propiedad, $foto);
            }

            if ($propiedad->video !== null) {
                $this->media->eliminarVideo($propiedad);
            }

            $this->auditar($propiedad, 'eliminada');
            $propiedad->delete();
        });
    }

    /**
     * Publica la propiedad validando los requisitos mínimos.
     *
     * @throws BusinessException Cuando falta información obligatoria.
     */
    public function publicar(Propiedad $propiedad): Propiedad
    {
        $propiedad->loadMissing('fotos');

        $faltantes = $propiedad->requisitosFaltantesParaPublicar();

        if ($faltantes !== []) {
            throw new BusinessException(
                __('messages.propiedad_no_publicable'),
                ['propiedad' => [__('messages.propiedad_no_publicable')]],
                422,
                'PROPIEDAD_NO_PUBLICABLE',
                ['requisitos_faltantes' => $faltantes],
            );
        }

        $propiedad->forceFill([
            'estado' => EstadoPropiedad::Publicada,
            'publicada_en' => $propiedad->publicada_en ?? now(),
            'vendida_en' => null,
        ])->save();

        $this->auditar($propiedad, 'publicada');
        $this->notificaciones->propiedadPublicada($this->propiedades->detalle($propiedad));

        return $propiedad->refresh();
    }

    /**
     * Pausa una publicación (deja de ser visible en el catálogo).
     */
    public function pausar(Propiedad $propiedad): Propiedad
    {
        $propiedad->forceFill(['estado' => EstadoPropiedad::Pausada])->save();

        $this->auditar($propiedad, 'pausada');

        return $propiedad->refresh();
    }

    /**
     * Cierra la operación (vendida/alquilada) y registra la conversión.
     */
    public function cerrarOperacion(Propiedad $propiedad, EstadoPropiedad $estado = EstadoPropiedad::Vendida): Propiedad
    {
        if (! $estado->esCerrada()) {
            throw new BusinessException(__('messages.cita_estado_invalido'), [], 422, 'ESTADO_INVALIDO');
        }

        DB::transaction(function () use ($propiedad, $estado): void {
            $propiedad->forceFill([
                'estado' => $estado,
                'vendida_en' => now(),
            ])->save();

            $this->reportes->registrar(
                TipoReporte::Conversion,
                $propiedad,
                valor: (float) $propiedad->precio,
                metadata: ['estado' => $estado->value, 'moneda' => $propiedad->moneda->value],
            );

            $this->auditar($propiedad, $estado === EstadoPropiedad::Vendida ? 'vendida' : 'alquilada');
        });

        return $propiedad->refresh();
    }

    /**
     * Sincroniza las amenidades del catálogo (valida el máximo configurado).
     *
     * @param  array<int, int|string>  $ids
     */
    public function sincronizarAmenidades(Propiedad $propiedad, array $ids): void
    {
        $limpios = collect($ids)
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->take((int) config('mercado.reglas.max_amenidades_por_propiedad', 25))
            ->values()
            ->all();

        $propiedad->amenidades()->sync($limpios);
    }

    /**
     * Registra una vista: contador desnormalizado + métrica diaria.
     */
    public function registrarVista(Propiedad $propiedad, ?Cliente $cliente = null): void
    {
        $propiedad->registrarVista();

        $this->reportes->registrar(TipoReporte::Vista, $propiedad, 1, $cliente);
    }

    /**
     * Detalle con relaciones y contadores cargados.
     */
    public function detalle(Propiedad $propiedad): Propiedad
    {
        return $this->propiedades->detalle($propiedad);
    }

    /**
     * Añade fotos a una propiedad existente.
     *
     * @param  array<int, UploadedFile>  $fotos
     * @return array<int, PropiedadFoto>
     */
    public function agregarFotos(Propiedad $propiedad, array $fotos): array
    {
        $creadas = [];

        DB::transaction(function () use ($propiedad, $fotos, &$creadas): void {
            foreach (array_values($fotos) as $foto) {
                $creadas[] = $this->media->subirFotoPropiedad($propiedad, $foto);
            }

            $this->auditar($propiedad, 'fotos_agregadas', ['cantidad' => count($creadas)]);
        });

        return $creadas;
    }

    /**
     * Registra en el log de auditoría la acción realizada sobre la propiedad.
     *
     * @param  array<string, mixed>  $propiedades
     */
    private function auditar(Propiedad $propiedad, string $evento, array $propiedades = []): void
    {
        try {
            activity()
                ->performedOn($propiedad)
                ->causedBy(auth()->user())
                ->withProperties($propiedades + [
                    'codigo' => $propiedad->codigo,
                    'estado' => $propiedad->estado->value,
                ])
                ->event($evento)
                ->log("propiedad {$evento}");
        } catch (Throwable) {
            // La auditoría nunca debe interrumpir la operación de negocio.
        }
    }
}
