<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea a usuarios desactivados por un administrador y se asegura de que el
 * perfil extendido (Inmobiliaria|Vendedor|Cliente) exista.
 */
final class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            if (! $user->is_active) {
                throw new AuthorizationException(__('messages.cuenta_inactiva'));
            }

            $user->loadMissing('perfil');
        }

        return $next($request);
    }
}
