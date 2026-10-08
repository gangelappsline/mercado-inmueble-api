<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel\Concerns;

use App\Exceptions\BusinessException;
use App\Models\Inmobiliaria;
use App\Models\Vendedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Resuelve el perfil anunciante (inmobiliaria o vendedor) del usuario
 * autenticado para las acciones del panel.
 */
trait ResuelvePropietario
{
    /**
     * @throws BusinessException 422 si la cuenta no tiene perfil anunciante.
     */
    protected function propietario(Request $request): Model
    {
        $propietario = $request->user()?->propietario();

        if (! $propietario instanceof Inmobiliaria && ! $propietario instanceof Vendedor) {
            throw new BusinessException(__('messages.perfil_no_encontrado'), [], 422, 'PERFIL_NO_ENCONTRADO');
        }

        return $propietario;
    }
}
