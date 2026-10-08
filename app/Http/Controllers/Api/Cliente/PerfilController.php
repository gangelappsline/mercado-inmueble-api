<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Cliente;

use App\Http\Controllers\Api\Cliente\Concerns\ResuelveCliente;
use App\Http\Controllers\Controller;
use App\Http\Requests\Perfil\UpdatePerfilRequest;
use App\Http\Resources\ClienteResource;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Perfil del cliente: datos de contacto, presupuesto y preferencias de
 * búsqueda usadas por la búsqueda personalizada.
 */
class PerfilController extends Controller
{
    use ResuelveCliente;

    /**
     * Perfil del cliente con sus contadores de actividad.
     */
    public function show(Request $request): JsonResponse
    {
        $cliente = $this->cliente($request)
            ->loadCount(['favoritos', 'intereses', 'citas'])
            ->load('user');

        return ApiResponse::success([
            'usuario' => new UserResource($request->user()),
            'perfil' => (new ClienteResource($cliente))->resolve($request),
        ], __('messages.ok'));
    }

    /**
     * Actualiza los datos del cliente y de su cuenta.
     */
    public function actualizar(UpdatePerfilRequest $request): JsonResponse
    {
        $cliente = $this->cliente($request);
        ['usuario' => $datosUsuario, 'perfil' => $datosPerfil] = $request->datosSeparados();

        if ($datosUsuario !== []) {
            $request->user()->update($datosUsuario);
        }

        if ($datosPerfil !== []) {
            $cliente->update($datosPerfil);
        }

        return ApiResponse::success([
            'usuario' => new UserResource($request->user()->refresh()),
            'perfil' => (new ClienteResource($cliente->refresh()))->resolve($request),
            'preferencias_de_busqueda' => $cliente->preferenciasDeBusqueda(),
        ], __('messages.perfil_actualizado'));
    }
}
