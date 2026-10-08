<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mensaje;
use App\Models\PropiedadVideo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega de archivos alojados en disks privados mediante URLs firmadas
 * (`signed` middleware). Nunca se exponen rutas directas del filesystem.
 */
class MediaController extends Controller
{
    /**
     * Descarga el video de una propiedad desde el disk privado.
     */
    public function video(PropiedadVideo $video): StreamedResponse
    {
        return Storage::disk($video->disk)->download(
            $video->ruta,
            $video->nombre_original ?? basename($video->ruta),
        );
    }

    /**
     * Descarga el adjunto de un mensaje desde el disk privado.
     */
    public function adjunto(Mensaje $mensaje): StreamedResponse
    {
        $disk = (string) config('mercado.media.disco_videos');

        return Storage::disk($disk)->download(
            (string) $mensaje->adjunto,
            basename((string) $mensaje->adjunto),
        );
    }
}
