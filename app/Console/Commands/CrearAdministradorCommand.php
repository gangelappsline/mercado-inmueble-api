<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Alta de cuentas con rol `administrador` (agentes de la plataforma).
 *
 * Es la vía de arranque del panel de administración: el registro público de la
 * API no permite crear administradores, así que la primera cuenta se crea por
 * consola (o desde el propio panel, una vez que ya existe un agente).
 *
 * También promueve una cuenta existente (por ejemplo una inmobiliaria que pasa
 * a formar parte del equipo) con `--promover`; al hacerlo revoca sus tokens
 * porque el scope OAuth asociado al rol cambia.
 */
class CrearAdministradorCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mercado:crear-admin
        {email? : Correo de la cuenta (por defecto, ADMIN_EMAIL)}
        {--nombre= : Nombre visible de la cuenta}
        {--telefono= : Teléfono de contacto}
        {--password= : Contraseña; si se omite se genera una segura y se imprime una única vez}
        {--promover : Convierte una cuenta existente en administrador}
        {--force : No pide confirmación (usado por mercado:instalar)}';

    /**
     * @var string
     */
    protected $description = 'Crea o promueve una cuenta con rol administrador (panel de administración)';

    /**
     * Crea o promueve la cuenta administrativa.
     */
    public function handle(AuthService $auth): int
    {
        $email = Str::lower(trim((string) ($this->argument('email') ?: config('mercado.admin.email'))));
        $nombre = trim((string) ($this->option('nombre') ?: config('mercado.admin.nombre')));
        $telefono = $this->option('telefono') !== null ? trim((string) $this->option('telefono')) : null;
        $password = $this->option('password') !== null ? (string) $this->option('password') : null;

        if ($email === '' || $nombre === '') {
            $this->components->error('Indica el correo (ADMIN_EMAIL) y el nombre (ADMIN_NOMBRE) de la cuenta.');

            return self::FAILURE;
        }

        $existente = User::withTrashed()->where('email', $email)->first();

        if ($existente instanceof User) {
            return $this->resolverExistente($existente, $password);
        }

        $generada = $password === null;
        $password = $password ?? Str::password(20);

        if (! $this->validar(['email' => $email, 'name' => $nombre, 'password' => $password])) {
            return self::FAILURE;
        }

        $usuario = $auth->crearCuenta(Role::Administrador, [
            'name' => $nombre,
            'email' => $email,
            'password' => $password,
            'phone' => $telefono,
            'is_active' => true,
        ]);

        $usuario->forceFill(['email_verified_at' => now()])->save();

        $this->components->info(sprintf('Cuenta de administrador creada: %s', $email));
        $this->mostrarCredenciales($email, $password, $generada);

        return self::SUCCESS;
    }

    /**
     * La cuenta ya existe: la promueve, la restaura o actualiza su contraseña.
     */
    private function resolverExistente(User $usuario, ?string $password): int
    {
        if ($usuario->trashed()) {
            $usuario->restore();
            $this->components->info('La cuenta estaba dada de baja y fue restaurada.');
        }

        if ($usuario->role !== Role::Administrador) {
            if (! $this->option('promover') && ! $this->confirmarPromocion($usuario)) {
                $this->components->warn(sprintf(
                    'La cuenta %s tiene el rol "%s". Usa --promover para convertirla en administrador.',
                    $usuario->email,
                    $usuario->role->value,
                ));

                return self::FAILURE;
            }

            $anterior = $usuario->role;

            $usuario->forceFill(['role' => Role::Administrador, 'is_active' => true])->save();
            $usuario->tokens()->delete();

            $this->components->info(sprintf(
                'Cuenta promovida a administrador: %s (rol anterior: %s). Se revocaron sus tokens.',
                $usuario->email,
                $anterior->value,
            ));
        } elseif ($usuario->is_active === false) {
            $usuario->forceFill(['is_active' => true])->save();
            $this->components->info('La cuenta de administrador fue reactivada.');
        } else {
            $this->components->info(sprintf('La cuenta %s ya es un administrador activo.', $usuario->email));
        }

        if ($password !== null) {
            if (! $this->validar(['password' => $password])) {
                return self::FAILURE;
            }

            $usuario->forceFill(['password' => Hash::make($password)])->save();
            $usuario->tokens()->delete();

            $this->mostrarCredenciales($usuario->email, $password, false);
        }

        return self::SUCCESS;
    }

    /**
     * Confirmación interactiva antes de cambiar el rol de una cuenta.
     */
    private function confirmarPromocion(User $usuario): bool
    {
        if ($this->option('force') || ! $this->getOutput()->isInteractive()) {
            return false;
        }

        return (bool) $this->components->confirm(sprintf(
            '¿Convertir la cuenta %s (rol %s) en administrador?',
            $usuario->email,
            $usuario->role->label(),
        ));
    }

    /**
     * Valida los datos con las reglas de la API y muestra los errores.
     *
     * @param  array<string, string>  $datos
     */
    private function validar(array $datos): bool
    {
        $reglas = array_intersect_key([
            'email' => ['required', 'string', 'email:rfc', 'max:180'],
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'password' => ['required', 'string', Password::defaults()],
        ], $datos);

        $validador = Validator::make($datos, $reglas);

        if ($validador->passes()) {
            return true;
        }

        foreach ($validador->errors()->all() as $error) {
            $this->components->error($error);
        }

        return false;
    }

    /**
     * Imprime las credenciales (la contraseña generada se muestra una sola vez).
     */
    private function mostrarCredenciales(string $email, string $password, bool $generada): void
    {
        $this->newLine();
        $this->table(['Cuenta de administración', 'Valor'], [
            ['Correo', $email],
            ['Rol', Role::Administrador->value],
            ['Contraseña', $password],
        ]);

        if ($generada) {
            $this->components->warn('Contraseña generada automáticamente: guárdala, no vuelve a mostrarse.');
        }

        $this->line('   Acceso: POST /api/v1/auth/login → panel /api/v1/admin/*');
    }
}
