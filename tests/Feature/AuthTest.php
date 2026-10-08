<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Flujo de autenticación: registro por rol, login, sesión y recuperación.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * El registro de inmobiliaria crea la cuenta, el perfil y devuelve tokens.
     */
    public function test_registro_de_inmobiliaria_crea_perfil_y_tokens(): void
    {
        Notification::fake();

        $respuesta = $this->postJson('/api/v1/auth/register/inmobiliaria', [
            'name' => 'Inmobiliaria Demo',
            'email' => 'nueva@demo.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+591 70000000',
            'razon_social' => 'Demo Propiedades S.R.L.',
            'ruc' => '1023456789',
            'ciudad' => 'La Paz',
            'acepta_terminos' => true,
        ], $this->cabeceras());

        $respuesta->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.usuario.role.value', 'inmobiliaria')
            ->assertJsonStructure(['data' => ['usuario' => ['id', 'email'], 'tokens']]);

        $this->assertDatabaseHas('users', ['email' => 'nueva@demo.test', 'role' => 'inmobiliaria']);
        $this->assertDatabaseHas('inmobiliarias', ['ruc' => '1023456789']);
    }

    /**
     * El registro de cliente exige aceptar los términos.
     */
    public function test_registro_de_cliente_valida_terminos(): void
    {
        $respuesta = $this->postJson('/api/v1/auth/register/cliente', [
            'name' => 'Cliente Demo',
            'email' => 'cliente@demo.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'acepta_terminos' => false,
        ], $this->cabeceras());

        $respuesta->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertDatabaseMissing('users', ['email' => 'cliente@demo.test']);
    }

    /**
     * El login devuelve el par de tokens y registra el último acceso.
     */
    public function test_login_exitoso_devuelve_tokens(): void
    {
        $usuario = $this->usuario(Role::Cliente);

        $respuesta = $this->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'password' => UserFactory::PASSWORD_DEMO,
        ], $this->cabeceras());

        $respuesta->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['usuario' => ['id', 'email'], 'tokens' => ['access_token', 'refresh_token', 'token_type', 'expires_in']]]);

        $this->assertNotNull($usuario->refresh()->last_login_at);
    }

    /**
     * Credenciales inválidas devuelven 401 con el sobre uniforme.
     */
    public function test_login_con_credenciales_invalidas_devuelve_401(): void
    {
        $usuario = $this->usuario(Role::Vendedor);

        $this->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'password' => 'incorrecta',
        ], $this->cabeceras())
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'CREDENCIALES_INVALIDAS');
    }

    /**
     * `/auth/me` devuelve el perfil extendido del usuario autenticado.
     */
    public function test_me_devuelve_perfil_del_usuario(): void
    {
        $usuario = $this->autenticar($this->usuario(Role::Inmobiliaria));

        $this->getJson('/api/v1/auth/me', $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('data.usuario.role.value', 'inmobiliaria')
            ->assertJsonPath('data.usuario.perfil.razon_social', $usuario->inmobiliaria->razon_social);
    }

    /**
     * Sin token, `/auth/me` responde 401.
     */
    public function test_me_sin_token_devuelve_401(): void
    {
        $this->getJson('/api/v1/auth/me', $this->cabeceras())
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    /**
     * El logout revoca el token en uso.
     */
    public function test_logout_cierra_la_sesion(): void
    {
        $this->autenticar($this->usuario(Role::Cliente));

        $this->postJson('/api/v1/auth/logout', [], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('message', __('messages.sesion_cerrada'));
    }

    /**
     * El correo de recuperación responde siempre con 200 (no revela correos).
     */
    public function test_forgot_password_no_revela_si_el_correo_existe(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'noexiste@demo.test'], $this->cabeceras())
            ->assertOk()
            ->assertJsonPath('message', __('messages.correo_enviado'));
    }

    /**
     * Un rol inexistente en la URL no puede registrarse.
     */
    public function test_registro_con_rol_invalido_devuelve_404(): void
    {
        $this->postJson('/api/v1/auth/register/administrador', [
            'name' => 'Intruso',
            'email' => 'intruso@demo.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], $this->cabeceras())->assertNotFound();
    }
}
