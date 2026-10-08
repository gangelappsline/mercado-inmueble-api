<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Propiedad;
use App\Models\PropiedadFoto;
use App\Models\PropiedadVideo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gestión de archivos: subida, optimización (redimensionado + thumbnail),
 * reemplazo y eliminación de fotos, videos y logotipos.
 *
 * Fotos  → disk público (`mercado.media.disco_fotos`).
 * Videos → disk privado o S3 (`mercado.media.disco_videos`).
 */
final class MediaService
{
    /**
     * Guarda una foto de la propiedad, genera su thumbnail y la asocia.
     *
     * @param  bool  $principal  Marca la foto como principal (desmarca el resto).
     */
    public function subirFotoPropiedad(Propiedad $propiedad, UploadedFile $archivo, bool $principal = false): PropiedadFoto
    {
        $config = config('mercado.media');
        $disk = (string) $config['disco_fotos'];

        $this->validarCantidadDeFotos($propiedad, (int) $config['max_fotos_por_propiedad']);

        return DB::transaction(function () use ($propiedad, $archivo, $principal, $disk, $config): PropiedadFoto {
            $ruta = $this->guardarArchivo($archivo, "propiedades/{$propiedad->getKey()}/fotos", $disk);
            $dimensiones = $this->optimizarImagen($ruta, $disk, (int) $config['ancho_max_foto']);
            $thumbnail = $config['generar_thumbnails']
                ? $this->generarThumbnail($ruta, $disk, (int) $config['ancho_thumbnail'], "propiedades/{$propiedad->getKey()}/thumbs")
                : null;

            $orden = (int) $propiedad->fotos()->max('orden') + 1;

            $foto = $propiedad->fotos()->create([
                'ruta' => $ruta,
                'disk' => $disk,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime' => $archivo->getClientMimeType(),
                'tamanio' => $this->pesoDe($ruta, $disk, $archivo),
                'ancho' => $dimensiones['ancho'],
                'alto' => $dimensiones['alto'],
                'thumbnail' => $thumbnail,
                'orden' => $orden,
                'es_principal' => false,
            ]);

            $esPrimera = $propiedad->fotos()->count() === 1;

            if ($principal || $esPrimera) {
                $this->marcarFotoPrincipal($propiedad, $foto);
            }

            return $foto->refresh();
        });
    }

    /**
     * Marca una foto como principal (y desmarca las demás).
     */
    public function marcarFotoPrincipal(Propiedad $propiedad, PropiedadFoto $foto): PropiedadFoto
    {
        if ((int) $foto->propiedad_id !== (int) $propiedad->getKey()) {
            throw new BusinessException(__('messages.foto_no_encontrada'), [], 404, 'FOTO_NO_ENCONTRADA');
        }

        DB::transaction(function () use ($propiedad, $foto): void {
            $propiedad->fotos()->whereKeyNot($foto->getKey())->update(['es_principal' => false]);
            $foto->forceFill(['es_principal' => true])->save();
        });

        return $foto->refresh();
    }

    /**
     * Reordena las fotos de una propiedad.
     *
     * @param  array<int, int>  $idsEnOrden
     */
    public function reordenarFotos(Propiedad $propiedad, array $idsEnOrden): void
    {
        DB::transaction(function () use ($propiedad, $idsEnOrden): void {
            foreach (array_values($idsEnOrden) as $posicion => $id) {
                $propiedad->fotos()->whereKey($id)->update(['orden' => $posicion]);
            }
        });
    }

    /**
     * Elimina una foto (archivo físico + registro).
     */
    public function eliminarFoto(Propiedad $propiedad, PropiedadFoto $foto): void
    {
        if ((int) $foto->propiedad_id !== (int) $propiedad->getKey()) {
            throw new BusinessException(__('messages.foto_no_encontrada'), [], 404, 'FOTO_NO_ENCONTRADA');
        }

        DB::transaction(function () use ($propiedad, $foto): void {
            $eraPrincipal = $foto->es_principal;

            $this->borrarArchivo($foto->ruta, $foto->disk);
            $this->borrarArchivo($foto->thumbnail, $foto->disk);
            $foto->delete();

            // Si se eliminó la principal, se promueve la primera disponible.
            if ($eraPrincipal) {
                $siguiente = $propiedad->fotos()->first();

                if ($siguiente !== null) {
                    $siguiente->forceFill(['es_principal' => true, 'orden' => 0])->save();
                }
            }
        });
    }

    /**
     * Sube el video de la propiedad. Sólo se admite UNO: si ya existe, se debe
     * eliminar antes (regla de negocio de PropiedadService/MediaService).
     */
    public function subirVideoPropiedad(Propiedad $propiedad, UploadedFile $archivo, bool $reemplazar = false): PropiedadVideo
    {
        $existente = $propiedad->video()->first();

        if ($existente !== null && ! $reemplazar) {
            throw BusinessException::conflicto(
                __('messages.propiedad_video_duplicado'),
                ['video' => [__('messages.propiedad_video_duplicado')]],
                'VIDEO_DUPLICADO',
            );
        }

        return DB::transaction(function () use ($propiedad, $archivo, $existente): PropiedadVideo {
            if ($existente !== null) {
                $this->borrarArchivo($existente->ruta, $existente->disk);
                $existente->delete();
            }

            $disk = (string) config('mercado.media.disco_videos');
            $ruta = $this->guardarArchivo($archivo, "propiedades/{$propiedad->getKey()}/videos", $disk);

            return $propiedad->video()->create([
                'ruta' => $ruta,
                'disk' => $disk,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime' => $archivo->getClientMimeType(),
                'tamanio' => $this->pesoDe($ruta, $disk, $archivo),
                'duracion' => null,
                'thumbnail' => null,
            ]);
        });
    }

    /**
     * Elimina el video de la propiedad.
     */
    public function eliminarVideo(Propiedad $propiedad): void
    {
        $video = $propiedad->video()->first();

        if ($video === null) {
            throw new BusinessException(__('messages.propiedad_sin_video'), [], 404, 'VIDEO_NO_ENCONTRADO');
        }

        DB::transaction(function () use ($video): void {
            $this->borrarArchivo($video->ruta, $video->disk);
            $video->delete();
        });
    }

    /**
     * Sube el logotipo/foto de un perfil (inmobiliaria o vendedor).
     *
     * @param  object  $perfil  Instancia con atributo `logo` o `foto`.
     * @param  string  $columna  Nombre del atributo que guarda la ruta.
     */
    public function subirImagenDePerfil(object $perfil, UploadedFile $archivo, string $columna = 'logo', string $carpeta = 'logos'): string
    {
        $disk = (string) config('mercado.media.disco_fotos');
        $rutaPrevia = $perfil->{$columna} ?? null;

        $ruta = $this->guardarArchivo($archivo, "{$carpeta}/{$perfil->getKey()}", $disk);
        $this->optimizarImagen($ruta, $disk, 800);

        if (is_string($rutaPrevia) && $rutaPrevia !== '') {
            $this->borrarArchivo($rutaPrevia, $disk);
        }

        return $ruta;
    }

    /**
     * Guarda un adjunto de mensaje en el disk privado y devuelve su ruta.
     */
    public function subirAdjuntoDeMensaje(\App\Models\Hilo $hilo, UploadedFile $archivo): string
    {
        $disk = (string) config('mercado.media.disco_videos');

        return $this->guardarArchivo($archivo, "mensajes/{$hilo->getKey()}", $disk);
    }

    /**
     * Escribe el archivo en el disk indicado con un nombre único.
     */
    private function guardarArchivo(UploadedFile $archivo, string $carpeta, string $disk): string
    {
        $nombre = sprintf('%s.%s', Str::uuid(), Str::lower($archivo->getClientOriginalExtension() ?: $archivo->extension()));

        return $archivo->storeAs($carpeta, $nombre, ['disk' => $disk]);
    }

    /**
     * Peso final del archivo almacenado (bytes).
     */
    private function pesoDe(string $ruta, string $disk, UploadedFile $archivo): int
    {
        try {
            return (int) Storage::disk($disk)->size($ruta);
        } catch (\Throwable) {
            return (int) $archivo->getSize();
        }
    }

    /**
     * Redimensiona la imagen si excede el ancho máximo (requiere ext-gd).
     *
     * @return array{ancho: int|null, alto: int|null}
     */
    private function optimizarImagen(string $ruta, string $disk, int $anchoMax): array
    {
        if (! extension_loaded('gd')) {
            return ['ancho' => null, 'alto' => null];
        }

        try {
            $contenido = Storage::disk($disk)->get($ruta);

            if ($contenido === null) {
                return ['ancho' => null, 'alto' => null];
            }

            $imagen = @imagecreatefromstring($contenido);

            if ($imagen === false) {
                return ['ancho' => null, 'alto' => null];
            }

            $ancho = imagesx($imagen);
            $alto = imagesy($imagen);

            if ($ancho <= $anchoMax) {
                imagedestroy($imagen);

                return ['ancho' => $ancho, 'alto' => $alto];
            }

            $escala = $anchoMax / $ancho;
            $nuevoAlto = (int) max(round($alto * $escala), 1);
            $redimensionada = imagescale($imagen, $anchoMax, $nuevoAlto);
            imagedestroy($imagen);

            if ($redimensionada === false) {
                return ['ancho' => $ancho, 'alto' => $alto];
            }

            ob_start();
            imagejpeg($redimensionada, null, 82);
            $optimizada = (string) ob_get_clean();
            imagedestroy($redimensionada);

            Storage::disk($disk)->put($ruta, $optimizada);

            return ['ancho' => $anchoMax, 'alto' => $nuevoAlto];
        } catch (\Throwable $e) {
            Log::warning('No se pudo optimizar la imagen', ['ruta' => $ruta, 'error' => $e->getMessage()]);

            return ['ancho' => null, 'alto' => null];
        }
    }

    /**
     * Genera el thumbnail de una foto ya almacenada.
     */
    private function generarThumbnail(string $ruta, string $disk, int $anchoDestino, string $carpeta): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        try {
            $contenido = Storage::disk($disk)->get($ruta);

            if ($contenido === null) {
                return null;
            }

            $imagen = @imagecreatefromstring($contenido);

            if ($imagen === false) {
                return null;
            }

            $ancho = imagesx($imagen);
            $alto = imagesy($imagen);
            $escala = min($anchoDestino / max($ancho, 1), 1);
            $thumbnail = imagescale($imagen, (int) max(round($ancho * $escala), 1), (int) max(round($alto * $escala), 1));
            imagedestroy($imagen);

            if ($thumbnail === false) {
                return null;
            }

            ob_start();
            imagejpeg($thumbnail, null, 78);
            $binario = (string) ob_get_clean();
            imagedestroy($thumbnail);

            $rutaThumb = sprintf('%s/%s.jpg', trim($carpeta, '/'), Str::uuid());
            Storage::disk($disk)->put($rutaThumb, $binario);

            return $rutaThumb;
        } catch (\Throwable $e) {
            Log::warning('No se pudo generar el thumbnail', ['ruta' => $ruta, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Borra un archivo tolerando rutas nulas o inexistentes.
     */
    private function borrarArchivo(?string $ruta, string $disk): void
    {
        if ($ruta === null || $ruta === '') {
            return;
        }

        try {
            Storage::disk($disk)->delete($ruta);
        } catch (\Throwable $e) {
            Log::warning('No se pudo eliminar el archivo', ['ruta' => $ruta, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Valida el límite de fotos por propiedad.
     */
    private function validarCantidadDeFotos(Propiedad $propiedad, int $maximo): void
    {
        if ($propiedad->fotos()->count() >= $maximo) {
            throw new BusinessException(
                __('messages.propiedad_limite_fotos'),
                ['fotos' => [__('messages.propiedad_limite_fotos')]],
                422,
                'LIMITE_FOTOS',
                ['maximo' => $maximo],
            );
        }
    }
}
