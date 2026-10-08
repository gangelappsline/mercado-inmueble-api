<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Api\Panel\Concerns\ResuelvePropietario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Propiedad\CerrarOperacionRequest;
use App\Http\Requests\Propiedad\IndexPropiedadPanelRequest;
use App\Http\Requests\Propiedad\StoreFotoRequest;
use App\Http\Requests\Propiedad\StorePropiedadRequest;
use App\Http\Requests\Propiedad\StoreVideoRequest;
use App\Http\Requests\Propiedad\UpdatePropiedadRequest;
use App\Http\Resources\PropiedadDetalleResource;
use App\Http\Resources\PropiedadFotoResource;
use App\Http\Resources\PropiedadResource;
use App\Http\Resources\PropiedadVideoResource;
use App\Http\Resources\ReporteResource;
use App\Models\Propiedad;
use App\Models\PropiedadFoto;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use App\Services\MediaService;
use App\Services\PropiedadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestión de publicaciones del panel (inmobiliaria y vendedor): CRUD, medios,
 * publicación/pausa y cierre de operación.
 *
 * Las subclases de rol (`Api\Inmobiliaria\PropiedadController` y
 * `Api\Vendedor\PropiedadController`) heredan el comportamiento y añaden el
 * contexto de su panel en la documentación.
 */
class PropiedadController extends Controller
{
    use ResuelvePropietario;

    public function __construct(
        private readonly PropiedadService $propiedades,
        private readonly PropiedadRepositoryInterface $repositorio,
        private readonly MediaService $media,
    ) {
    }

    /**
     * Listado del panel con filtros por estado y resumen por estado.
     */
    public function index(IndexPropiedadPanelRequest $request): JsonResponse
    {
        $propietario = $this->propietario($request);

        $paginado = $this->repositorio->listarParaPropietario(
            $propietario,
            $request->filtros(),
            $request->porPagina(),
        );

        return ApiResponse::success(
            PropiedadResource::collection($paginado),
            __('messages.ok'),
            meta: ['resumen_por_estado' => $this->repositorio->resumenPorEstado($propietario)],
        );
    }

    /**
     * Crea una propiedad (opcionalmente con fotos, video y publicación directa).
     */
    public function store(StorePropiedadRequest $request): JsonResponse
    {
        $propietario = $this->propietario($request);

        $propiedad = $this->propiedades->crear(
            $propietario,
            $request->datosDeLaPropiedad(),
            array_values($request->file('fotos', []) ?? []),
            $request->file('video'),
        );

        $advertencia = null;

        if ($request->publicarAlCrear()) {
            try {
                $propiedad = $this->propiedades->publicar($propiedad);
            } catch (BusinessException $e) {
                $advertencia = $e->getMessage();
            }
        }

        return ApiResponse::created(
            (new PropiedadDetalleResource($propiedad))->resolve($request),
            __('messages.creado'),
            $advertencia !== null ? ['advertencia' => $advertencia] : [],
        );
    }

    /**
     * Detalle administrativo de una publicación propia.
     */
    public function show(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('view', $propiedad);

        return ApiResponse::success(
            (new PropiedadDetalleResource($this->propiedades->detalle($propiedad)))->resolve($request),
            __('messages.ok'),
        );
    }

    /**
     * Actualiza los datos de la publicación (no toca fotos ni video).
     */
    public function update(UpdatePropiedadRequest $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('update', $propiedad);

        $datos = $request->datosDeLaPropiedad();

        if (array_key_exists('amenidades', $datos)) {
            $this->propiedades->sincronizarAmenidades($propiedad, (array) $datos['amenidades']);
            unset($datos['amenidades']);
        }

        $propiedad = $this->propiedades->actualizar($propiedad, $datos);

        return ApiResponse::success(
            (new PropiedadDetalleResource($propiedad))->resolve($request),
            __('messages.actualizado'),
        );
    }

    /**
     * Elimina la publicación y sus medios (borrado lógico).
     */
    public function destroy(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('delete', $propiedad);

        $this->propiedades->eliminar($propiedad);

        return ApiResponse::success(null, __('messages.eliminado'));
    }

    /**
     * Publica la propiedad si cumple los requisitos mínimos.
     */
    public function publicar(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('publicar', $propiedad);

        $propiedad = $this->propiedades->publicar($propiedad);

        return ApiResponse::success(
            (new PropiedadDetalleResource($propiedad))->resolve($request),
            __('messages.propiedad_publicada'),
        );
    }

    /**
     * Pausa la publicación (deja de ser visible en el catálogo).
     */
    public function pausar(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('pausar', $propiedad);

        $propiedad = $this->propiedades->pausar($propiedad);

        return ApiResponse::success(
            (new PropiedadDetalleResource($propiedad))->resolve($request),
            __('messages.propiedad_pausada'),
        );
    }

    /**
     * Cierra la operación como venta o alquiler.
     */
    public function cerrarOperacion(CerrarOperacionRequest $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('cerrar', $propiedad);

        $propiedad = $this->propiedades->cerrarOperacion($propiedad, $request->estadoDeCierre());

        return ApiResponse::success(
            (new PropiedadDetalleResource($propiedad))->resolve($request),
            __('messages.actualizado'),
        );
    }

    /**
     * Métricas de la publicación: contadores y reportes de los últimos 30 días.
     */
    public function estadisticas(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('verEstadisticas', $propiedad);

        $reportes = $propiedad->reportes()
            ->orderByDesc('fecha')
            ->limit(90)
            ->get();

        return ApiResponse::success([
            'contadores' => [
                'vistas' => $propiedad->vistas_count,
                'contactos' => $propiedad->contactos_count,
                'favoritos' => $propiedad->favoritos_count,
                'citas' => $propiedad->citas_count,
            ],
            'reportes' => ReporteResource::collection($reportes)->resolve($request),
        ], __('messages.reporte_generado'));
    }

    /**
     * Agrega una o varias fotos a la galería.
     */
    public function subirFotos(StoreFotoRequest $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('update', $propiedad);

        $fotos = $this->propiedades->agregarFotos($propiedad, array_values($request->file('fotos', []) ?? []));

        if ($request->boolean('principal') && $fotos !== []) {
            $this->media->marcarFotoPrincipal($propiedad, $fotos[0]);
        }

        return ApiResponse::created(
            PropiedadFotoResource::collection(collect($fotos))->resolve($request),
            __('messages.archivo_subido'),
        );
    }

    /**
     * Marca una foto como principal.
     */
    public function fotoPrincipal(Request $request, Propiedad $propiedad, PropiedadFoto $foto): JsonResponse
    {
        $this->authorize('update', $propiedad);
        $this->verificarPertenencia($propiedad, $foto);

        $this->media->marcarFotoPrincipal($propiedad, $foto);

        return ApiResponse::success(
            (new PropiedadFotoResource($foto->refresh()))->resolve($request),
            __('messages.foto_principal_actualizada'),
        );
    }

    /**
     * Reordena la galería según el arreglo de ids recibido.
     */
    public function reordenarFotos(Request $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('update', $propiedad);

        $datos = $request->validate([
            'fotos' => ['required', 'array', 'min:1'],
            'fotos.*' => ['integer', 'distinct'],
        ], [
            'fotos.required' => 'Envía el arreglo de ids en el nuevo orden.',
        ]);

        $this->media->reordenarFotos($propiedad, array_map('intval', $datos['fotos']));

        return ApiResponse::success(
            PropiedadFotoResource::collection($propiedad->fotos()->get())->resolve($request),
            __('messages.actualizado'),
        );
    }

    /**
     * Elimina una foto de la galería (no puede quedar la propiedad sin fotos
     * si está publicada).
     */
    public function eliminarFoto(Request $request, Propiedad $propiedad, PropiedadFoto $foto): JsonResponse
    {
        $this->authorize('update', $propiedad);
        $this->verificarPertenencia($propiedad, $foto);

        $this->media->eliminarFoto($propiedad, $foto);

        return ApiResponse::success(null, __('messages.eliminado'));
    }

    /**
     * Sube o reemplaza el video de la propiedad (máximo uno).
     */
    public function subirVideo(StoreVideoRequest $request, Propiedad $propiedad): JsonResponse
    {
        $this->authorize('update', $propiedad);

        $video = $this->media->subirVideoPropiedad(
            $propiedad,
            $request->file('video'),
            $request->boolean('reemplazar'),
        );

        return ApiResponse::created(
            (new PropiedadVideoResource($video))->resolve($request),
            __('messages.archivo_subido'),
        );
    }

    /**
     * Elimina el video de la propiedad.
     */
    public function eliminarVideo(Propiedad $propiedad): JsonResponse
    {
        $this->authorize('update', $propiedad);

        $this->media->eliminarVideo($propiedad);

        return ApiResponse::success(null, __('messages.propiedad_sin_video'));
    }

    /**
     * Verifica que la foto pertenezca a la propiedad de la ruta.
     */
    private function verificarPertenencia(Propiedad $propiedad, PropiedadFoto $foto): void
    {
        if ((int) $foto->propiedad_id !== (int) $propiedad->getKey()) {
            abort(404, __('messages.foto_no_encontrada'));
        }
    }
}
