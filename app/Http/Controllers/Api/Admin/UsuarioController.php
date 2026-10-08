<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CambiarRolRequest;
use App\Http\Requests\Admin\IndexUsuarioRequest;
use App\Http\Requests\Admin\StoreUsuarioRequest;
use App\Http\Requests\Admin\UpdateUsuarioRequest;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Auth\RegisterClienteRequest;
use App\Http\Requests\Auth\RegisterInmobiliariaRequest;
use App\Http\Requests\Auth\RegisterVendedorRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdministracionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestión de cuentas de la plataforma (`/admin/usuarios`).
 *
 * El agente puede dar de alta cuentas de cualquier rol, editarlas, suspenderlas
 * o reactivarlas, reasignar su rol y darlas de baja. Las acciones nunca se
 * aplican sobre la propia cuenta y siempre debe quedar al menos un
 * administrador activo (ver `AdministracionService`).
 */
class UsuarioController extends Controller
{
    public function __construct(
        private readonly AdministracionService $administracion,
    ) {
    }

    /**
     * Listado global de cuentas con filtros por rol, estado y búsqueda.
     */
    public function index(IndexUsuarioRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $paginado = $this->administracion->listarUsuarios($request->filtros(), $request->porPagina());

        return ApiResponse::success(
            UserResource::collection($paginado),
            __('messages.ok'),
            meta: [
                'administradores_activos' => $this->administracion->administradoresActivos(),
                'roles' => Role::opciones(),
            ],
        );
    }

    /**
     * Detalle de la cuenta con el resumen de su actividad.
     */
    public function show(Request $request, User $usuario): JsonResponse
    {
        $this->authorize('view', $usuario);

        $usuario->loadMissing('perfil');
        $propietario = $usuario->propietario();

        return ApiResponse::success([
            'usuario' => (new UserResource($usuario))->resolve($request),
            'resumen' => [
                'perfil_type' => $usuario->perfil_type,
                'propiedades' => $propietario !== null ? $propietario->propiedades()->count() : 0,
                'mensajes' => $usuario->mensajes()->count(),
            ],
        ], __('messages.ok'));
    }

    /**
     * Crea una cuenta del rol indicado (incluidas cuentas de administrador).
     *
     * El cuerpo de la petición coincide con el registro público del rol
     * (`role` + los campos del perfil extendido).
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validado = $request->validate([
            'role' => ['required', Rule::in(Role::valores())],
        ], [
            'role.required' => 'Indica el rol de la cuenta que deseas crear.',
            'role.in' => 'El rol indicado no existe en el catálogo de la plataforma.',
        ]);

        $rol = Role::from((string) $validado['role']);
        $formulario = $this->formularioDeAlta($request, $rol);
        $formulario->setContainer(app())->setRedirector(app('redirect'));
        $formulario->validateResolved();

        $usuario = $this->administracion->crearUsuario($rol, $formulario->validated());

        return ApiResponse::created(
            ['usuario' => new UserResource($usuario)],
            __('messages.admin_usuario_creado'),
        );
    }

    /**
     * Edita los datos de contacto o la contraseña de una cuenta.
     */
    public function update(UpdateUsuarioRequest $request, User $usuario): JsonResponse
    {
        $this->authorize('update', $usuario);

        $actualizado = $this->administracion->actualizarUsuario($usuario, $request->datosDeLaCuenta());

        return ApiResponse::success(
            ['usuario' => new UserResource($actualizado)],
            __('messages.admin_usuario_actualizado'),
        );
    }

    /**
     * Reactiva una cuenta suspendida.
     */
    public function activar(Request $request, User $usuario): JsonResponse
    {
        $this->authorize('activar', $usuario);

        $actualizado = $this->administracion->cambiarEstado($usuario, true);

        return ApiResponse::success(
            ['usuario' => new UserResource($actualizado)],
            __('messages.admin_usuario_activado'),
        );
    }

    /**
     * Suspende una cuenta y revoca todas sus sesiones.
     */
    public function desactivar(Request $request, User $usuario): JsonResponse
    {
        $this->authorize('desactivar', $usuario);

        $actualizado = $this->administracion->cambiarEstado($usuario, false);

        return ApiResponse::success(
            ['usuario' => new UserResource($actualizado)],
            __('messages.admin_usuario_desactivado'),
        );
    }

    /**
     * Reasigna el rol de la cuenta (revoca sus tokens: el scope cambia).
     */
    public function cambiarRol(CambiarRolRequest $request, User $usuario): JsonResponse
    {
        $this->authorize('cambiarRol', $usuario);

        $actualizado = $this->administracion->cambiarRol($usuario, $request->rol());

        return ApiResponse::success(
            ['usuario' => new UserResource($actualizado)],
            __('messages.admin_rol_actualizado'),
        );
    }

    /**
     * Baja lógica de la cuenta (conserva sus registros históricos).
     */
    public function destroy(Request $request, User $usuario): JsonResponse
    {
        $this->authorize('delete', $usuario);

        $this->administracion->eliminarUsuario($usuario);

        return ApiResponse::success(null, __('messages.admin_usuario_eliminado'));
    }

    /**
     * Restaura una cuenta dada de baja.
     */
    public function restaurar(Request $request, User $usuario): JsonResponse
    {
        $this->authorize('restore', $usuario);

        $restaurado = $this->administracion->restaurarUsuario($usuario);

        return ApiResponse::success(
            ['usuario' => new UserResource($restaurado)],
            __('messages.admin_usuario_restaurado'),
        );
    }

    /**
     * Form Request del alta según el rol solicitado: los roles con perfil
     * extendido reutilizan las reglas del registro público.
     */
    private function formularioDeAlta(Request $request, Role $rol): ApiFormRequest
    {
        $clase = match ($rol) {
            Role::Administrador => StoreUsuarioRequest::class,
            Role::Inmobiliaria => RegisterInmobiliariaRequest::class,
            Role::Vendedor => RegisterVendedorRequest::class,
            Role::Cliente => RegisterClienteRequest::class,
        };

        /** @var ApiFormRequest $formulario */
        $formulario = $clase::createFrom($request);

        return $formulario;
    }
}
