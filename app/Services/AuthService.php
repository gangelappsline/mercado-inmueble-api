<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\BusinessException;
use App\Models\Cliente;
use App\Models\Inmobiliaria;
use App\Models\Propiedad;
use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\ClientRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Throwable;

/**
 * Autenticación OAuth2 con Laravel Passport.
 *
 * Estrategia:
 *  - El login usa el grant `password` contra el AuthorizationServer interno,
 *    porque es el único flujo de Passport que emite refresh tokens. Los clientes
 *    first-party (app móvil / SPA) se crean con `php artisan mercado:instalar`.
 *  - Si el cliente first-party no está configurado, se emite un token personal
 *    (sin refresh token) para que el entorno de desarrollo siga funcionando.
 *  - La rotación de refresh tokens la aplica Passport (revoca el anterior).
 */
final class AuthService
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
        private readonly ClientRepository $clientes,
    ) {
    }

    /**
     * Registra un usuario con su perfil extendido según el rol.
     *
     * @param  array<string, mixed>  $datos
     * @return array{user: User, tokens: array<string, mixed>}
     */
    public function registrar(Role $rol, array $datos): array
    {
        $usuario = DB::transaction(function () use ($rol, $datos): User {
            /** @var User $usuario */
            $usuario = User::create([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'role' => $rol,
                'phone' => $datos['phone'] ?? null,
                'is_active' => true,
            ]);

            $perfil = match ($rol) {
                Role::Inmobiliaria => $this->crearInmobiliaria($usuario, $datos),
                Role::Vendedor => $this->crearVendedor($usuario, $datos),
                Role::Cliente => $this->crearCliente($usuario, $datos),
            };

            $usuario->vincularPerfil($perfil);

            return $usuario;
        });

        $this->notificaciones->bienvenida($usuario->loadMissing('perfil'));

        return [
            'user' => $usuario->loadMissing('perfil'),
            'tokens' => $this->emitirTokens($usuario, (string) $datos['password']),
        ];
    }

    /**
     * Autentica por correo y contraseña.
     *
     * @return array{user: User, tokens: array<string, mixed>}
     *
     * @throws BusinessException 401 credenciales inválidas, 403 cuenta inactiva.
     */
    public function login(string $email, string $password, ?Request $request = null): array
    {
        /** @var User|null $usuario */
        $usuario = User::query()->where('email', $email)->first();

        if ($usuario === null || ! Hash::check($password, $usuario->password)) {
            throw new BusinessException(
                __('messages.credenciales_invalidas'),
                ['email' => [__('messages.credenciales_invalidas')]],
                401,
                'CREDENCIALES_INVALIDAS',
            );
        }

        if (! $usuario->is_active) {
            throw new BusinessException(
                __('messages.cuenta_inactiva'),
                [],
                403,
                'CUENTA_INACTIVA',
            );
        }

        $usuario->registrarAcceso();

        return [
            'user' => $usuario->loadMissing('perfil'),
            'tokens' => $this->emitirTokens($usuario, $password, $request),
        ];
    }

    /**
     * Renueva el par de tokens a partir de un refresh token.
     *
     * @return array<string, mixed>
     *
     * @throws BusinessException 401 cuando el refresh token es inválido o expiró.
     */
    public function refrescar(string $refreshToken): array
    {
        [$clientId, $clientSecret] = $this->credencialesFirstParty();

        if ($clientId === null) {
            throw new BusinessException(
                __('messages.token_invalido'),
                [],
                401,
                'OAUTH_NO_CONFIGURADO',
            );
        }

        $respuesta = $this->solicitarTokenAlServidor([
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => (string) $clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        return $this->normalizarTokens($respuesta);
    }

    /**
     * Revoca el token con el que se autenticó la petición.
     *
     * Al revocar el access token, Passport también rechaza su refresh token.
     */
    public function cerrarSesion(User $usuario): void
    {
        $token = $usuario->token();

        if ($token !== null) {
            $token->revoke();
        }
    }

    /**
     * Cierra la sesión en todos los dispositivos.
     */
    public function cerrarTodasLasSesiones(User $usuario): void
    {
        foreach ($usuario->tokens as $token) {
            $token->revoke();
        }
    }

    /**
     * Envía el correo de recuperación de contraseña.
     *
     * Por seguridad responde igual exista o no la cuenta (evita enumeración de
     * usuarios): el correo sólo se envía si el usuario existe.
     *
     * @throws BusinessException
     */
    public function enviarEnlaceDeRecuperacion(string $email): void
    {
        /** @var User|null $usuario */
        $usuario = User::query()->where('email', $email)->first();

        if ($usuario === null) {
            return;
        }

        $token = Password::broker()->createToken($usuario);

        $this->notificaciones->recuperarPassword($usuario, $token);
    }

    /**
     * Restablece la contraseña con un token válido.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    public function restablecerPassword(array $datos): void
    {
        $resultado = Password::broker()->reset(
            [
                'email' => $datos['email'],
                'password' => $datos['password'],
                'password_confirmation' => $datos['password_confirmation'],
                'token' => $datos['token'],
            ],
            function (CanResetPassword $usuario, string $password): void {
                /** @var User $usuario */
                $usuario->forceFill(['password' => Hash::make($password)])->save();

                // Al cambiar la contraseña se invalidan todas las sesiones.
                $usuario->tokens()->delete();
            },
        );

        if ($resultado === Password::PASSWORD_RESET) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => [(string) __($resultado)],
        ]);
    }

    /**
     * Emite los tokens del usuario (access + refresh) para el login/registro.
     *
     * @return array<string, mixed>
     *
     * @throws BusinessException
     */
    public function emitirTokens(User $usuario, string $password, ?Request $request = null): array
    {
        [$clientId, $clientSecret] = $this->credencialesFirstParty();

        if ($clientId !== null) {
            try {
                return $this->normalizarTokens($this->solicitarTokenAlServidor([
                    'grant_type' => 'password',
                    'client_id' => $clientId,
                    'client_secret' => (string) $clientSecret,
                    'username' => $usuario->email,
                    'password' => $password,
                    'scope' => $usuario->role->scope(),
                ]));
            } catch (OAuthServerException $e) {
                // Las credenciales ya se validaron antes de llegar aquí.
                throw new BusinessException(
                    __('messages.credenciales_invalidas'),
                    [],
                    401,
                    'CREDENCIALES_INVALIDAS',
                    config('app.debug') ? ['oauth_error' => $e->getMessage()] : [],
                    $e,
                );
            }
        }

        return $this->tokenPersonal($usuario, $request);
    }

    /**
     * Token personal de Passport (fallback sin cliente first-party).
     *
     * @return array<string, mixed>
     */
    private function tokenPersonal(User $usuario, ?Request $request = null): array
    {
        $resultado = $usuario->createToken(
            $this->nombreDelDispositivo($request),
            [$usuario->role->scope()],
        );

        return [
            'access_token' => $resultado->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $resultado->expiresIn,
            'refresh_token' => null,
            'scope' => $usuario->role->scope(),
            'modo' => 'personal_access',
        ];
    }

    /**
     * Credenciales del cliente first-party (config/mercado.php → PASSPORT_CLIENT_*).
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function credencialesFirstParty(): array
    {
        $clientId = config('mercado.oauth.client_id');
        $clientSecret = config('mercado.oauth.client_secret');

        if (blank($clientId) || blank($clientSecret)) {
            return [null, null];
        }

        return [(string) $clientId, (string) $clientSecret];
    }

    /**
     * Ejecuta una petición contra el AuthorizationServer interno de Passport
     * (mismo mecanismo que usa el endpoint /oauth/token).
     *
     * @param  array<string, string>  $parametros
     * @return array<string, mixed>
     *
     * @throws OAuthServerException
     */
    private function solicitarTokenAlServidor(array $parametros): array
    {
        $peticion = (new PsrHttpFactory)->createRequest(
            Request::create((string) config('app.url'), 'POST', $parametros)
        );

        $respuesta = app(AuthorizationServer::class)->respondToAccessTokenRequest(
            $peticion,
            app(ResponseInterface::class),
        );

        /** @var array<string, mixed> $datos */
        $datos = json_decode((string) $respuesta->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $datos;
    }

    /**
     * Normaliza la respuesta del servidor OAuth al contrato de la API.
     *
     * @param  array<string, mixed>  $respuesta
     * @return array<string, mixed>
     */
    private function normalizarTokens(array $respuesta): array
    {
        return [
            'access_token' => $respuesta['access_token'] ?? null,
            'token_type' => $respuesta['token_type'] ?? 'Bearer',
            'expires_in' => $respuesta['expires_in'] ?? null,
            'refresh_token' => $respuesta['refresh_token'] ?? null,
            'scope' => $respuesta['scope'] ?? null,
            'modo' => 'oauth_password',
        ];
    }

    /**
     * Nombre del token personal: identifica el dispositivo en la lista de
     * sesiones activas del usuario.
     */
    private function nombreDelDispositivo(?Request $request): string
    {
        $userAgent = (string) ($request?->userAgent() ?? '');

        return $userAgent !== ''
            ? mb_substr($userAgent, 0, 120)
            : 'Mercado Inmueble API';
    }

    /**
     * Crea el perfil de inmobiliaria.
     *
     * @param  array<string, mixed>  $datos
     */
    private function crearInmobiliaria(User $usuario, array $datos): Inmobiliaria
    {
        return Inmobiliaria::create([
            'user_id' => $usuario->getKey(),
            'razon_social' => $datos['razon_social'],
            'nombre_comercial' => $datos['nombre_comercial'] ?? $datos['razon_social'],
            'ruc' => $datos['ruc'],
            'direccion' => $datos['direccion'] ?? null,
            'telefono' => $datos['telefono'] ?? $usuario->phone,
            'web' => $datos['web'] ?? null,
            'descripcion' => $datos['descripcion'] ?? null,
            'ciudad' => $datos['ciudad'],
            'estado_provincia' => $datos['estado_provincia'],
            'pais' => $datos['pais'] ?? config('mercado.pais_por_defecto', 'Bolivia'),
        ]);
    }

    /**
     * Crea el perfil de vendedor.
     *
     * @param  array<string, mixed>  $datos
     */
    private function crearVendedor(User $usuario, array $datos): Vendedor
    {
        return Vendedor::create([
            'user_id' => $usuario->getKey(),
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'dni' => $datos['dni'],
            'telefono' => $datos['telefono'] ?? $usuario->phone,
            'direccion' => $datos['direccion'] ?? null,
            'ciudad' => $datos['ciudad'] ?? null,
            'estado_provincia' => $datos['estado_provincia'] ?? null,
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'biografia' => $datos['biografia'] ?? null,
        ]);
    }

    /**
     * Crea el perfil de cliente.
     *
     * @param  array<string, mixed>  $datos
     */
    private function crearCliente(User $usuario, array $datos): Cliente
    {
        return Cliente::create([
            'user_id' => $usuario->getKey(),
            'telefono' => $datos['telefono'] ?? $usuario->phone,
            'presupuesto_min' => $datos['presupuesto_min'] ?? null,
            'presupuesto_max' => $datos['presupuesto_max'] ?? null,
            'moneda' => $datos['moneda'] ?? config('mercado.moneda_por_defecto', 'BOB'),
            'tipo_propiedad_interes' => $datos['tipo_propiedad_interes'] ?? null,
            'ciudad_interes' => $datos['ciudad_interes'] ?? null,
            'habitaciones_min' => $datos['habitaciones_min'] ?? null,
            'acepta_terminos' => (bool) ($datos['acepta_terminos'] ?? true),
            'recibe_novedades' => (bool) ($datos['recibe_novedades'] ?? true),
        ]);
    }

    /**
     * Cantidad de propiedades publicadas por un anunciante (para el resumen de /me).
     */
    public function propiedadesPublicadas(User $usuario): int
    {
        $propietario = $usuario->propietario();

        if ($propietario === null) {
            return 0;
        }

        return Propiedad::query()->delPropietario($propietario)->publicadas()->count();
    }

    /**
     * Crea los clientes OAuth first-party si no existen (usado por el instalador).
     *
     * @return array{client_id: string|null, client_secret: string|null, personal_client_id: string|null}
     */
    public function asegurarClientesOAuth(string $nombre = 'Mercado Inmueble App'): array
    {
        try {
            $password = $this->clientes->createPasswordGrantClient($nombre, 'users', true);
            $personal = $this->clientes->createPersonalAccessGrantClient($nombre.' (tokens personales)', 'users');
        } catch (Throwable $e) {
            throw new BusinessException(
                'No se pudieron crear los clientes OAuth: '.$e->getMessage(),
                [],
                500,
                'OAUTH_SETUP_FALLIDO',
                [],
                $e,
            );
        }

        return [
            'client_id' => $password->getKey(),
            'client_secret' => (string) $password->plainSecret,
            'personal_client_id' => $personal->getKey(),
        ];
    }

    /**
     * Perfil extendido del usuario o error de negocio si no existe.
     *
     * @throws BusinessException
     */
    public function perfilDe(User $usuario): Model
    {
        $perfil = $usuario->perfil;

        if (! $perfil instanceof Model) {
            throw new BusinessException(__('messages.perfil_no_encontrado'), [], 422, 'PERFIL_NO_ENCONTRADO');
        }

        return $perfil;
    }
}
