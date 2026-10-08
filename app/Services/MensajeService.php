<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\BusinessException;
use App\Models\Cliente;
use App\Models\Hilo;
use App\Models\Interes;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Conversaciones entre el cliente interesado y el anunciante.
 */
final class MensajeService
{
    /**
     * Roles que participan de un hilo de mensajes.
     */
    private const ROLES_PARTICIPANTES = [Role::Inmobiliaria, Role::Vendedor, Role::Cliente];

    public function __construct(
        private readonly NotificacionService $notificaciones,
        private readonly MediaService $media,
    ) {
    }

    /**
     * Bandeja de entrada del usuario autenticado (hilos, no mensajes sueltos).
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Hilo>
     */
    public function hilosPara(User $user, array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $query = Hilo::query()
            ->with([
                'interes.propiedad.fotoPrincipal',
                'interes.propiedad:id,titulo,codigo,ciudad,estado_provincia',
                'interes.cliente.user:id,name,email',
                'ultimoMensaje',
            ]);

        if ($user->hasRole(Role::Cliente)) {
            $query->delCliente($this->clienteDe($user));
        } else {
            $query->delPropietario($this->propietarioDe($user));
        }

        if (filter_var($filtros['solo_no_leidos'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->conNoLeidos($user->role);
        }

        $estado = $filtros['estado'] ?? null;

        if ($estado === 'abiertos') {
            $query->abiertos();
        } elseif ($estado === 'cerrados') {
            $query->where('cerrado', true);
        }

        if (filled($filtros['q'] ?? null)) {
            $comodin = '%'.$filtros['q'].'%';
            $query->where('asunto', 'like', $comodin);
        }

        return $query->recientes()->paginate($porPagina)->withQueryString();
    }

    /**
     * Detalle del hilo: valida participación, marca como leído y devuelve los
     * mensajes en orden cronológico.
     */
    public function detalle(Hilo $hilo, User $user): Hilo
    {
        $this->autorizarParticipante($hilo, $user);

        $hilo->marcarLeidoPor($user->role);

        return $hilo->load([
            'mensajes.autor:id,name,role',
            'interes.propiedad.fotoPrincipal',
            'interes.cliente.user:id,name,email',
        ])->refresh();
    }

    /**
     * Publica un mensaje en el hilo y notifica a la contraparte.
     */
    public function enviar(Hilo $hilo, User $user, string $cuerpo, ?UploadedFile $adjunto = null): Mensaje
    {
        $this->autorizarParticipante($hilo, $user);

        if ($hilo->cerrado) {
            throw BusinessException::conflicto(__('messages.hilo_cerrado'), [], 'HILO_CERRADO');
        }

        $adjuntoRuta = $adjunto !== null
            ? $this->media->subirAdjuntoDeMensaje($hilo, $adjunto)
            : null;

        $mensaje = DB::transaction(function () use ($hilo, $user, $cuerpo, $adjuntoRuta): Mensaje {
            /** @var Mensaje $mensaje */
            $mensaje = $hilo->mensajes()->create([
                'user_id' => $user->getKey(),
                'rol_autor' => $user->role,
                'cuerpo' => $cuerpo,
                'adjunto' => $adjuntoRuta,
            ]);

            $hilo->registrarMensaje($user->role);

            return $mensaje;
        });

        $mensaje->setRelation('hilo', $hilo->refresh());

        if ($user->hasRole(Role::Cliente)) {
            $this->notificaciones->respuestaDelCliente($mensaje);
        } else {
            $this->notificaciones->respuestaDelPropietario($mensaje);
        }

        return $mensaje->load('autor:id,name,role');
    }

    /**
     * Devuelve (o crea) el hilo asociado a un interés.
     */
    public function hiloDeInteres(Interes $interes): Hilo
    {
        return Hilo::query()->firstOrCreate(
            ['interes_id' => $interes->getKey()],
            [
                'asunto' => $interes->propiedad !== null
                    ? Hilo::asuntoPara($interes->propiedad)
                    : 'Consulta',
                'ultimo_mensaje_en' => $interes->created_at ?? now(),
            ],
        );
    }

    /**
     * Cierra o reabre un hilo.
     */
    public function cambiarEstado(Hilo $hilo, bool $cerrado, User $user): Hilo
    {
        $this->autorizarParticipante($hilo, $user);

        if ($cerrado) {
            $hilo->cerrar();
        } else {
            $hilo->reabrir();
        }

        $this->auditar($hilo, $cerrado ? 'cerrado' : 'reabierto');

        return $hilo->refresh();
    }

    /**
     * Valida que el usuario participe del hilo.
     *
     * @throws BusinessException 404 si el hilo no existe para él, 403 si no participa.
     */
    private function autorizarParticipante(Hilo $hilo, User $user): void
    {
        if (! in_array($user->role, self::ROLES_PARTICIPANTES, true)) {
            throw new BusinessException(__('messages.hilo_no_encontrado'), [], 404, 'HILO_NO_ENCONTRADO');
        }

        if ($hilo->participa($user)) {
            return;
        }

        throw new BusinessException(__('messages.hilo_no_encontrado'), [], 404, 'HILO_NO_ENCONTRADO');
    }

    /**
     * Perfil de cliente del usuario (validado por el middleware `role:cliente`).
     *
     * @throws BusinessException 422 si el usuario no tiene perfil de cliente.
     */
    private function clienteDe(User $user): Cliente
    {
        $cliente = $user->perfilCliente() ?? $user->cliente()->first();

        if ($cliente === null) {
            throw new BusinessException(__('messages.perfil_no_encontrado'), [], 422, 'PERFIL_NO_ENCONTRADO');
        }

        return $cliente;
    }

    /**
     * Perfil de anunciante del usuario (inmobiliaria|vendedor).
     */
    private function propietarioDe(User $user): \Illuminate\Database\Eloquent\Model
    {
        $propietario = $user->propietario();

        if ($propietario === null) {
            throw new BusinessException(__('messages.perfil_no_encontrado'), [], 422, 'PERFIL_NO_ENCONTRADO');
        }

        return $propietario;
    }

    /**
     * Registro de auditoría del cambio de estado del hilo.
     */
    private function auditar(Hilo $hilo, string $evento): void
    {
        try {
            activity()->performedOn($hilo)->causedBy(auth()->user())->event($evento)->log("hilo {$evento}");
        } catch (Throwable) {
            // nunca interrumpe la operación
        }
    }
}
