<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware `role:inmobiliaria`, `role:vendedor`, `role:cliente`.
 * Acepta varios roles: `role:inmobiliaria,vendedor`.
 */
final class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  ...$roles  Roles permitidos (valores de App\Enums\Role).
     *
     * @throws AuthenticationException Cuando no hay token válido.
     * @throws AuthorizationException  Cuando el rol no está permitido.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException(__('messages.no_autenticado'));
        }

        if (! $user->hasAnyRole($roles)) {
            throw new AuthorizationException(__('messages.rol_no_autorizado'));
        }

        return $next($request);
    }
}
