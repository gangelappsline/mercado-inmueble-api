<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexContactoRequest;
use App\Http\Resources\ContactoResource;
use App\Models\Contacto;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bandeja de mensajes del formulario público de contacto (`/admin/contactos`).
 *
 * Es el buzón de soporte de la plataforma: los mensajes llegan por
 * `POST /api/v1/contacto` y aquí se consultan, se marcan como atendidos y se
 * eliminan.
 */
class ContactoController extends Controller
{
    public function __construct(
        private readonly AdministracionService $administracion,
    ) {
    }

    /**
     * Listado de mensajes (pendientes primero).
     */
    public function index(IndexContactoRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Contacto::class);

        $paginado = $this->administracion->listarContactos($request->filtros(), $request->porPagina());

        return ApiResponse::success(
            ContactoResource::collection($paginado),
            __('messages.ok'),
            meta: ['pendientes' => Contacto::query()->noAtendidos()->count()],
        );
    }

    /**
     * Detalle del mensaje con su trazabilidad.
     */
    public function show(Request $request, Contacto $contacto): JsonResponse
    {
        $this->authorize('view', $contacto);

        return ApiResponse::success(
            (new ContactoResource($contacto))->resolve($request),
            __('messages.ok'),
        );
    }

    /**
     * Marca el mensaje como atendido.
     */
    public function atender(Request $request, Contacto $contacto): JsonResponse
    {
        $this->authorize('atender', $contacto);

        $atendido = $this->administracion->atenderContacto($contacto);

        return ApiResponse::success(
            (new ContactoResource($atendido))->resolve($request),
            __('messages.admin_contacto_atendido'),
        );
    }

    /**
     * Elimina el mensaje de la bandeja.
     */
    public function destroy(Request $request, Contacto $contacto): JsonResponse
    {
        $this->authorize('delete', $contacto);

        $this->administracion->eliminarContacto($contacto);

        return ApiResponse::success(null, __('messages.eliminado'));
    }
}
