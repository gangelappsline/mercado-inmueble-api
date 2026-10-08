<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Panel;

use App\Http\Controllers\Api\Panel\Concerns\ResuelvePropietario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Perfil\StoreLogoRequest;
use App\Http\Requests\Perfil\UpdatePerfilRequest;
use App\Http\Resources\InmobiliariaResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VendedorResource;
use App\Models\Inmobiliaria;
use App\Models\Vendedor;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use App\Services\MediaService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Perfil del anunciante: datos de la cuenta, datos del negocio, logotipo o
 * fotografía y resumen de publicaciones por estado.
 */
class PerfilController extends Controller
{
    use ResuelvePropietario;

    public function __construct(
        private readonly MediaService $media,
        private readonly PropiedadRepositoryInterface $propiedades,
    ) {
    }

    /**
     * Perfil completo del anunciante con el resumen de sus publicaciones.
     */
    public function show(Request $request): JsonResponse
    {
        $propietario = $this->propietario($request);
        $usuario = $request->user()->loadMissing(['perfil', 'inmobiliaria', 'vendedor']);

        return ApiResponse::success([
            'usuario' => (new UserResource($usuario))->resolve($request),
            'perfil' => $this->perfilResuelto($request, $propietario),
            'resumen_publicaciones' => $this->propiedades->resumenPorEstado($propietario),
        ], __('messages.ok'));
    }

    /**
     * Actualiza los datos de la cuenta y del perfil del anunciante.
     */
    public function actualizar(UpdatePerfilRequest $request): JsonResponse
    {
        $propietario = $this->propietario($request);
        $usuario = $request->user();
        ['usuario' => $datosUsuario, 'perfil' => $datosPerfil] = $request->datosSeparados();

        if ($datosUsuario !== []) {
            $usuario->update($datosUsuario);
        }

        if ($datosPerfil !== []) {
            $propietario->update($datosPerfil);
        }

        $usuario->unsetRelation('perfil')->loadMissing(['perfil', 'inmobiliaria', 'vendedor']);

        return ApiResponse::success([
            'usuario' => (new UserResource($usuario))->resolve($request),
            'perfil' => $this->perfilResuelto($request, $propietario->refresh()),
        ], __('messages.perfil_actualizado'));
    }

    /**
     * Sube (o reemplaza) el logotipo de la inmobiliaria o la foto del vendedor.
     */
    public function subirLogo(StoreLogoRequest $request): JsonResponse
    {
        $propietario = $this->propietario($request);
        $columna = $propietario instanceof Vendedor ? 'foto' : 'logo';
        $carpeta = $propietario instanceof Vendedor ? 'vendedores' : 'logos';

        $ruta = $this->media->subirImagenDePerfil($propietario, $request->file('logo'), $columna, $carpeta);
        $propietario->update([$columna => $ruta]);

        return ApiResponse::success([
            'url' => $propietario->refresh()->urlLogo(),
        ], __('messages.logo_actualizado'));
    }

    /**
     * Elimina el logotipo o la fotografía actual.
     */
    public function eliminarLogo(Request $request): JsonResponse
    {
        $propietario = $this->propietario($request);
        $columna = $propietario instanceof Vendedor ? 'foto' : 'logo';

        if (filled($propietario->{$columna})) {
            Storage::disk('public')->delete((string) $propietario->{$columna});
        }

        $propietario->update([$columna => null]);

        return ApiResponse::success(null, __('messages.eliminado'));
    }

    /**
     * Resource del perfil según el tipo de anunciante.
     *
     * @return array<string, mixed>
     */
    private function perfilResuelto(Request $request, object $propietario): array
    {
        $recurso = $propietario instanceof Inmobiliaria
            ? new InmobiliariaResource($propietario)
            : new VendedorResource($propietario);

        /** @var array<string, mixed> $datos */
        $datos = $recurso->resolve($request);

        return $datos;
    }
}
