<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cliente\Concerns;

use App\Exceptions\BusinessException;
use App\Models\Cliente;
use Illuminate\Http\Request;

/**
 * Resuelve el perfil de cliente del usuario autenticado para las acciones del
 * panel de cliente.
 */
trait ResuelveCliente
{
    /**
     * @throws BusinessException 422 si la cuenta no tiene perfil de cliente.
     */
    protected function cliente(Request $request): Cliente
    {
        $cliente = $request->user()?->perfilCliente();

        if (! $cliente instanceof Cliente) {
            throw new BusinessException(__('messages.perfil_no_encontrado'), [], 422, 'PERFIL_NO_ENCONTRADO');
        }

        return $cliente;
    }
}
