<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * Caso de prueba base de la API.
 *
 * - Genera las claves de Passport una sola vez (necesarias para emitir tokens).
 * - Crea el cliente de tokens personales que usa `User::createToken()`.
 * - Ofrece helpers para usuarios de cada rol y para autenticar con Passport.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Prepara el entorno de autenticación antes de cada prueba.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys', ['--force' => true]);
        }

        $tieneClientePersonal = Passport::client()->newQuery()
            ->where('revoked', false)
            ->get()
            ->contains(static fn ($cliente): bool => $cliente->hasGrantType('personal_access'));

        if (! $tieneClientePersonal) {
            app(ClientRepository::class)->createPersonalAccessGrantClient('Testing');
        }
    }

    /**
     * Crea un usuario con su perfil extendido según el rol.
     */
    protected function usuario(Role $rol): User
    {
        return match ($rol) {
            Role::Inmobiliaria => User::factory()->inmobiliaria()->create(),
            Role::Vendedor => User::factory()->vendedor()->create(),
            Role::Cliente => User::factory()->cliente()->create(),
        };
    }

    /**
     * Autentica al usuario con un token de Passport del scope de su rol.
     */
    protected function autenticar(User $usuario): User
    {
        Passport::actingAs($usuario, [$usuario->role->scope()]);

        return $usuario;
    }

    /**
     * Cabeceras estándar de la API.
     *
     * @return array<string, string>
     */
    protected function cabeceras(): array
    {
        return ['Accept' => 'application/json'];
    }
}
