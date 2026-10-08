<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoInteres;
use App\Enums\EstadoPropiedad;
use App\Enums\TipoReporte;
use App\Exceptions\BusinessException;
use App\Models\Cliente;
use App\Models\Hilo;
use App\Models\Interes;
use App\Models\Mensaje;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Embudo comercial: registro del interés, creación del hilo de conversación y
 * respuestas del anunciante.
 */
final class InteresService
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
        private readonly MensajeService $mensajes,
        private readonly ReporteService $reportes,
    ) {
    }

    /**
     * Registra el interés de un cliente por una propiedad.
     *
     * Efectos: crea el interés, abre el hilo de conversación, incrementa el
     * contador de contactos, registra la métrica y notifica al anunciante.
     *
     * @throws BusinessException 409 si el cliente ya registró interés.
     */
    public function registrar(
        Propiedad $propiedad,
        Cliente $cliente,
        ?string $mensaje = null,
        string $origen = 'web',
    ): Interes {
        $this->validarRegistro($propiedad, $cliente);

        return DB::transaction(function () use ($propiedad, $cliente, $mensaje, $origen): Interes {
            /** @var Interes $interes */
            $interes = $propiedad->intereses()->create([
                'cliente_id' => $cliente->getKey(),
                'mensaje' => $mensaje,
                'estado' => EstadoInteres::Nuevo,
                'origen' => $origen,
            ]);

            $hilo = $this->mensajes->hiloDeInteres($interes);

            if (filled($mensaje)) {
                $autor = $cliente->user;

                if ($autor !== null) {
                    $hilo->mensajes()->create([
                        'user_id' => $autor->getKey(),
                        'rol_autor' => $autor->role,
                        'cuerpo' => $mensaje,
                    ]);

                    $hilo->registrarMensaje($autor->role);
                }
            }

            $propiedad->increment('contactos_count');

            $this->reportes->registrar(TipoReporte::Contacto, $propiedad, 1, $cliente);

            return $interes->load(['propiedad.propietario.user', 'cliente.user', 'hilo']);
        });
    }

    /**
     * El anunciante responde a un interesado (crea el mensaje en su hilo).
     */
    public function responder(Interes $interes, User $usuario, string $cuerpo, ?UploadedFile $adjunto = null): Mensaje
    {
        $hilo = $this->hiloDe($interes);

        $mensaje = $this->mensajes->enviar($hilo, $usuario, $cuerpo, $adjunto);

        if (! $interes->estado->esFinal()) {
            $interes->marcarAtendido();
        }

        return $mensaje;
    }

    /**
     * Cambia el estado del interés (atención, cierre o descarte).
     */
    public function cambiarEstado(Interes $interes, EstadoInteres $estado): Interes
    {
        switch ($estado) {
            case EstadoInteres::Atendido:
                $interes->marcarAtendido();
                break;
            case EstadoInteres::Cerrado:
                $interes->cerrar();
                break;
            case EstadoInteres::Descartado:
                $interes->descartar();
                break;
            default:
                $interes->forceFill(['estado' => $estado])->save();
                break;
        }

        $this->auditar($interes, $estado->value);

        return $interes->refresh();
    }

    /**
     * Interesados recibidos por un anunciante.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Interes>
     */
    public function listarParaPropietario(Model $propietario, array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        return Interes::query()
            ->delPropietario($propietario)
            ->with([
                'propiedad:id,titulo,codigo,ciudad,precio,moneda',
                'propiedad.fotoPrincipal',
                'cliente.user:id,name,email,phone',
                'hilo:id,interes_id,asunto,no_leidos_propietario,total_mensajes,cerrado,ultimo_mensaje_en',
            ])
            ->porEstado($filtros['estado'] ?? null)
            ->when(
                filter_var($filtros['solo_nuevos'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn ($query) => $query->nuevos(),
            )
            ->when(filled($filtros['propiedad_id'] ?? null), fn ($query) => $query->where('propiedad_id', $filtros['propiedad_id']))
            ->when(filled($filtros['q'] ?? null), fn ($query) => $query->where('mensaje', 'like', '%'.$filtros['q'].'%'))
            ->recientes()
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Intereses registrados por un cliente.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Interes>
     */
    public function listarParaCliente(Cliente $cliente, array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        return Interes::query()
            ->delCliente($cliente)
            ->with([
                'propiedad.fotoPrincipal',
                'propiedad.propietario',
                'hilo:id,interes_id,asunto,no_leidos_cliente,total_mensajes,cerrado,ultimo_mensaje_en',
            ])
            ->porEstado($filtros['estado'] ?? null)
            ->recientes()
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Hilo de mensajes del interés (lo crea si no existiera).
     */
    public function hiloDe(Interes $interes): Hilo
    {
        return $this->mensajes->hiloDeInteres($interes);
    }

    /**
     * Validaciones de negocio del registro de interés.
     *
     * @throws BusinessException
     */
    private function validarRegistro(Propiedad $propiedad, Cliente $cliente): void
    {
        if ($propiedad->estado !== EstadoPropiedad::Publicada) {
            throw new BusinessException(
                __('messages.propiedad_no_encontrada'),
                ['propiedad' => [__('messages.propiedad_no_encontrada')]],
                404,
                'PROPIEDAD_NO_DISPONIBLE',
            );
        }

        if ($propiedad->esDelUsuario($cliente->user)) {
            throw BusinessException::conflicto(
                __('messages.interes_propia_propiedad'),
                ['propiedad' => [__('messages.interes_propia_propiedad')]],
                'INTERES_PROPIA_PROPIEDAD',
            );
        }

        $yaRegistrado = Interes::withTrashed()
            ->where('propiedad_id', $propiedad->getKey())
            ->where('cliente_id', $cliente->getKey())
            ->exists();

        if ($yaRegistrado) {
            throw BusinessException::conflicto(
                __('messages.interes_duplicado'),
                ['propiedad' => [__('messages.interes_duplicado')]],
                'INTERES_DUPLICADO',
            );
        }
    }

    /**
     * Auditoría del cambio de estado del interés.
     */
    private function auditar(Interes $interes, string $estado): void
    {
        try {
            activity()
                ->performedOn($interes)
                ->causedBy(auth()->user())
                ->event('estado_cambiado')
                ->withProperties(['estado' => $estado, 'propiedad_id' => $interes->propiedad_id])
                ->log('interes actualizado');
        } catch (Throwable) {
            // nunca interrumpe la operación
        }
    }
}
