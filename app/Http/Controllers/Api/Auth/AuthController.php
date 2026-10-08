<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterClienteRequest;
use App\Http\Requests\Auth\RegisterInmobiliariaRequest;
use App\Http\Requests\Auth\RegisterVendedorRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autenticación OAuth2 (Passport) y gestión de la sesión.
 *
 * El registro es polimórfico: `POST /auth/register/{rol}` crea la cuenta y su
 * perfil extendido (inmobiliaria, vendedor o cliente) en una transacción.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {
    }

    /**
     * Registra una cuenta nueva según el rol de la ruta.
     */
    public function registrar(Request $request, string $rol): JsonResponse
    {
        $enum = Role::tryFrom($rol);

        if ($enum === null) {
            return ApiResponse::error(__('messages.rol_no_autorizado'), 404, 'ROL_INVALIDO');
        }

        $formulario = $this->formularioDeRegistro($request, $enum);
        $formulario->setContainer(app())->setRedirector(app('redirect'));
        $formulario->validateResolved();

        $resultado = $this->auth->registrar($enum, $formulario->validated());

        return ApiResponse::created([
            'usuario' => new UserResource($resultado['user']),
            'tokens' => $resultado['tokens'],
        ], __('messages.registro_exitoso'));
    }

    /**
     * Inicia sesión con correo y contraseña (grant `password`).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $resultado = $this->auth->login($datos['email'], $datos['password'], $request);

        return ApiResponse::success([
            'usuario' => new UserResource($resultado['user']),
            'tokens' => $resultado['tokens'],
        ], __('messages.ok'));
    }

    /**
     * Renueva el token de acceso a partir del refresh token.
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->auth->refrescar($request->validated('refresh_token'));

        return ApiResponse::success($tokens, __('messages.token_refrescado'));
    }

    /**
     * Perfil del usuario autenticado con su perfil extendido.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user()->loadMissing('perfil');

        return ApiResponse::success([
            'usuario' => new UserResource($usuario),
            'propiedades_publicadas' => $usuario->publicaPropiedades()
                ? $this->auth->propiedadesPublicadas($usuario)
                : 0,
            'scopes' => $request->user()->currentAccessToken()?->scopes ?? [],
        ], __('messages.perfil_actualizado'));
    }

    /**
     * Cierra la sesión actual (revoca el token en uso).
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $this->auth->cerrarSesion($usuario);

        return ApiResponse::success(null, __('messages.sesion_cerrada'));
    }

    /**
     * Cierra todas las sesiones del usuario (revoca todos los tokens).
     */
    public function logoutTodas(Request $request): JsonResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $this->auth->cerrarTodasLasSesiones($usuario);

        return ApiResponse::success(null, __('messages.sesion_cerrada'));
    }

    /**
     * Envía el enlace de recuperación de contraseña (respuesta genérica).
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->enviarEnlaceDeRecuperacion($request->validated('email'));

        return ApiResponse::success(null, __('messages.correo_enviado'));
    }

    /**
     * Restablece la contraseña con el token del correo.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->auth->restablecerPassword($request->validated());

        return ApiResponse::success(null, __('messages.password_restablecido'));
    }

    /**
     * Resuelve el Form Request del rol recibido en la URL.
     */
    private function formularioDeRegistro(Request $request, Role $rol): ApiFormRequest
    {
        $clase = match ($rol) {
            Role::Inmobiliaria => RegisterInmobiliariaRequest::class,
            Role::Vendedor => RegisterVendedorRequest::class,
            Role::Cliente => RegisterClienteRequest::class,
        };

        /** @var ApiFormRequest $formulario */
        $formulario = $clase::createFrom($request);

        return $formulario;
    }
}
