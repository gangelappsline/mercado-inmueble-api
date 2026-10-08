<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perfil\UpdatePerfilRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Perfil del propio agente de administración (`/admin/perfil`).
 *
 * Las cuentas administrativas no tienen perfil extendido: sólo se editan los
 * datos de la cuenta (nombre, correo y teléfono). El resto del endpoint
 * devuelve el catálogo de permisos del rol, útil para pintar el menú del panel.
 */
class PerfilController extends Controller
{
    /**
     * Accesos del rol `administrador` (menú y permisos del panel).
     *
     * @var array<string, array<int, string>>
     */
    private const PERMISOS = [
        'dashboard' => ['resumen_global'],
        'usuarios' => ['ver', 'crear', 'editar', 'activar', 'suspender', 'cambiar_rol', 'dar_de_baja', 'restaurar'],
        'inmobiliarias' => ['ver', 'verificar', 'retirar_verificacion'],
        'vendedores' => ['ver', 'verificar', 'retirar_verificacion'],
        'propiedades' => ['ver', 'destacar', 'publicar', 'pausar', 'rechazar', 'dar_de_baja', 'restaurar'],
        'contactos' => ['ver', 'atender', 'eliminar'],
        'auditoria' => ['ver'],
    ];

    public function __construct(
        private readonly AdministracionService $administracion,
    ) {
    }

    /**
     * Cuenta del agente autenticado con sus permisos.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return ApiResponse::success([
            'usuario' => (new UserResource($usuario->loadMissing('perfil')))->resolve($request),
            'permisos' => self::PERMISOS,
            'administradores_activos' => $this->administracion->administradoresActivos(),
        ], __('messages.ok'));
    }

    /**
     * Actualiza los datos de la cuenta del agente.
     */
    public function actualizar(UpdatePerfilRequest $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        ['usuario' => $datosUsuario] = $request->datosSeparados();

        if ($datosUsuario !== []) {
            $usuario->update($datosUsuario);
        }

        return ApiResponse::success(
            ['usuario' => new UserResource($usuario->refresh())],
            __('messages.perfil_actualizado'),
        );
    }
}
