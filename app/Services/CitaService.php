<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoCita;
use App\Enums\TipoCita;
use App\Enums\TipoReporte;
use App\Exceptions\BusinessException;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Interes;
use App\Models\Propiedad;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Agenda de visitas: solicitud, confirmación, reprogramación y cancelación.
 *
 * Reglas clave:
 *  - No se agenda en el pasado ni fuera de la ventana de anticipación.
 *  - No se permiten solapamientos para el anunciante ni para el cliente.
 *  - Cada transición avisa por correo y notificación (NotificacionService).
 */
final class CitaService
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
        private readonly ReporteService $reportes,
    ) {
    }

    /**
     * Solicita una cita para ver una propiedad.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws BusinessException
     */
    public function solicitar(
        Propiedad $propiedad,
        Cliente $cliente,
        array $datos,
        ?Interes $interes = null,
    ): Cita {
        $propietario = $propiedad->propietario;

        if ($propietario === null) {
            throw new BusinessException(__('messages.propiedad_no_encontrada'), [], 404, 'PROPIEDAD_SIN_PROPIETARIO');
        }

        $fecha = (string) $datos['fecha'];
        $hora = $this->normalizarHora((string) $datos['hora']);
        $duracion = (int) ($datos['duracion_minutos'] ?? config('mercado.reglas.duracion_cita_minutos', 30));
        $tipo = $datos['tipo'] instanceof TipoCita ? $datos['tipo'] : TipoCita::from((string) $datos['tipo']);

        $this->validarVentanaTemporal($fecha, $hora, $duracion);
        $this->validarSolapamiento($propietario, $fecha, $hora, $duracion);
        $this->validarAgendaDelCliente($cliente, $fecha, $hora, $duracion);

        $cita = DB::transaction(function () use ($propiedad, $cliente, $propietario, $datos, $fecha, $hora, $duracion, $tipo, $interes): Cita {
            /** @var Cita $cita */
            $cita = new Cita([
                'propiedad_id' => $propiedad->getKey(),
                'cliente_id' => $cliente->getKey(),
                'interes_id' => $interes?->getKey() ?? $datos['interes_id'] ?? null,
                'fecha' => $fecha,
                'hora' => $hora,
                'duracion_minutos' => $duracion,
                'tipo' => $tipo,
                'estado' => EstadoCita::Pendiente,
                'lugar' => $tipo === TipoCita::Visita
                    ? ($datos['lugar'] ?? $propiedad->direccion)
                    : ($datos['lugar'] ?? null),
                'enlace_virtual' => $tipo === TipoCita::Virtual
                    ? ($datos['enlace_virtual'] ?? $this->generarEnlaceVirtual())
                    : null,
                'notas' => $datos['notas'] ?? null,
            ]);

            $cita->propietario()->associate($propietario);
            $cita->save();

            $propiedad->increment('citas_count');

            $this->reportes->registrar(TipoReporte::Cita, $propiedad, 1, $cliente);

            return $cita;
        });

        $cita = $cita->load(['propiedad.propietario.user', 'cliente.user']);

        if ($interes !== null && ! $interes->estado->esFinal()) {
            $interes->marcarAtendido();
        }

        $this->auditar($cita, 'solicitada');
        $this->notificaciones->nuevaCita($cita);

        return $cita;
    }

    /**
     * Confirma una cita pendiente o reprogramada.
     *
     * @throws BusinessException
     */
    public function confirmar(Cita $cita, User $usuario): Cita
    {
        if (! $cita->puedeConfirmarse()) {
            throw new BusinessException(
                __('messages.cita_estado_invalido'),
                ['estado' => [__('messages.cita_estado_invalido')]],
                422,
                'CITA_ESTADO_INVALIDO',
                ['estado_actual' => $cita->estado->value],
            );
        }

        $cita->confirmar();

        $this->auditar($cita, 'confirmada');
        $this->notificaciones->citaConfirmada($cita->load(['propiedad', 'cliente.user', 'propietario.user']));

        return $cita->refresh();
    }

    /**
     * Reprograma una cita validando el nuevo horario.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws BusinessException
     */
    public function reprogramar(Cita $cita, array $datos, User $usuario): Cita
    {
        if (! $cita->puedeReprogramarse()) {
            throw new BusinessException(
                __('messages.cita_estado_invalido'),
                ['estado' => [__('messages.cita_estado_invalido')]],
                422,
                'CITA_ESTADO_INVALIDO',
            );
        }

        $fechaAnterior = $cita->inicio()->translatedFormat('d \d\e F \d\e Y');
        $horaAnterior = $cita->inicio()->format('H:i');

        $fecha = (string) ($datos['fecha'] ?? $cita->fecha?->toDateString());
        $hora = $this->normalizarHora((string) ($datos['hora'] ?? $cita->hora));
        $duracion = (int) ($datos['duracion_minutos'] ?? $cita->duracion_minutos);

        $this->validarVentanaTemporal($fecha, $hora, $duracion);
        $this->validarSolapamiento($cita->propietario, $fecha, $hora, $duracion, excepto: $cita);

        DB::transaction(function () use ($cita, $fecha, $hora, $duracion, $datos): void {
            $cita->duracion_minutos = $duracion;
            $cita->reprogramar($fecha, $hora, isset($datos['motivo']) ? (string) $datos['motivo'] : null);
        });

        $this->auditar($cita, 'reprogramada');
        $this->notificaciones->citaReprogramada(
            $cita->load(['propiedad', 'cliente.user', 'propietario.user']),
            $fechaAnterior,
            $horaAnterior,
        );

        return $cita->refresh();
    }

    /**
     * Cancela una cita indicando quién la canceló.
     *
     * @throws BusinessException
     */
    public function cancelar(Cita $cita, User $usuario, ?string $motivo = null): Cita
    {
        if (! $cita->puedeCancelarse()) {
            throw new BusinessException(
                __('messages.cita_estado_invalido'),
                ['estado' => [__('messages.cita_estado_invalido')]],
                422,
                'CITA_ESTADO_INVALIDO',
            );
        }

        $canceladaPor = $cita->esDelPropietario($usuario) ? 'anunciante' : 'cliente';

        $cita->cancelar($motivo);

        $this->auditar($cita, 'cancelada');
        $this->notificaciones->citaCancelada(
            $cita->load(['propiedad', 'cliente.user', 'propietario.user']),
            $canceladaPor,
        );

        return $cita->refresh();
    }

    /**
     * Marca la cita como completada (se realizó la visita).
     */
    public function completar(Cita $cita, User $usuario): Cita
    {
        if ($cita->estado->esFinal()) {
            throw new BusinessException(__('messages.cita_estado_invalido'), [], 422, 'CITA_ESTADO_INVALIDO');
        }

        $cita->completar();

        $this->auditar($cita, 'completada');

        return $cita->refresh();
    }

    /**
     * Marca la cita como no asistida.
     */
    public function marcarNoAsistio(Cita $cita, User $usuario): Cita
    {
        if ($cita->estado->esFinal()) {
            throw new BusinessException(__('messages.cita_estado_invalido'), [], 422, 'CITA_ESTADO_INVALIDO');
        }

        $cita->marcarNoAsistio();

        $this->auditar($cita, 'no_asistio');

        return $cita->refresh();
    }

    /**
     * Citas de un anunciante con filtros y paginación.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Cita>
     */
    public function listarParaPropietario(Model $propietario, array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        return Cita::query()
            ->delPropietario($propietario)
            ->with(['propiedad:id,titulo,codigo,ciudad,direccion', 'propiedad.fotoPrincipal', 'cliente.user:id,name,email,phone'])
            ->porEstado($filtros['estado'] ?? null)
            ->when(filled($filtros['desde'] ?? null) && filled($filtros['hasta'] ?? null), fn ($query) => $query->enRangoDeFechas($filtros['desde'], $filtros['hasta']))
            ->when(filled($filtros['fecha'] ?? null), fn ($query) => $query->deFecha($filtros['fecha']))
            ->when(filled($filtros['propiedad_id'] ?? null), fn ($query) => $query->where('propiedad_id', $filtros['propiedad_id']))
            ->when(
                filter_var($filtros['proximas'] ?? true, FILTER_VALIDATE_BOOLEAN),
                fn ($query) => $query->proximas(),
                fn ($query) => $query->orderByDesc('fecha')->orderByDesc('hora'),
            )
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Agenda del día: citas ordenadas por hora.
     *
     * @return Collection<int, Cita>
     */
    public function agendaDelDia(Model $propietario, string $fecha): Collection
    {
        return Cita::query()
            ->delPropietario($propietario)
            ->deFecha($fecha)
            ->with(['propiedad:id,titulo,codigo,direccion', 'cliente.user:id,name,email,phone'])
            ->get();
    }

    /**
     * Citas de un cliente.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Cita>
     */
    public function listarParaCliente(Cliente $cliente, array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        return Cita::query()
            ->delCliente($cliente)
            ->with(['propiedad:id,titulo,codigo,ciudad,direccion', 'propiedad.fotoPrincipal', 'propietario'])
            ->porEstado($filtros['estado'] ?? null)
            ->when(filled($filtros['fecha'] ?? null), fn ($query) => $query->deFecha($filtros['fecha']))
            ->orderByDesc('fecha')
            ->orderByDesc('hora')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Valida que la fecha/hora esté en la ventana permitida y sea futura.
     *
     * @throws BusinessException
     */
    private function validarVentanaTemporal(string $fecha, string $hora, int $duracion): void
    {
        $minimo = (int) config('mercado.reglas.duracion_cita_minutos', 30);
        $maximo = (int) config('mercado.reglas.duracion_cita_max_minutos', 240);

        if ($duracion < $minimo || $duracion > $maximo) {
            throw new BusinessException(
                __('messages.datos_invalidos'),
                ['duracion_minutos' => [sprintf('La duración debe estar entre %d y %d minutos.', $minimo, $maximo)]],
                422,
                'DURACION_INVALIDA',
            );
        }

        $inicio = Carbon::parse($fecha.' '.$hora);

        if ($inicio->isPast()) {
            throw new BusinessException(
                __('messages.cita_fecha_invalida'),
                ['fecha' => [__('messages.cita_fecha_invalida')]],
                422,
                'CITA_EN_EL_PASADO',
            );
        }

        $diasMaximos = (int) config('mercado.reglas.dias_anticipacion_cita_max', 90);

        if ($inicio->greaterThan(now()->addDays($diasMaximos))) {
            throw new BusinessException(
                __('messages.cita_fuera_de_rango'),
                ['fecha' => [__('messages.cita_fuera_de_rango')]],
                422,
                'CITA_FUERA_DE_RANGO',
                ['dias_maximos' => $diasMaximos],
            );
        }
    }

    /**
     * Valida que no exista otra cita activa en el mismo horario.
     *
     * @throws BusinessException
     */
    private function validarSolapamiento(
        ?Model $propietario,
        string $fecha,
        string $hora,
        int $duracion,
        ?Cita $excepto = null,
    ): void {
        if ($propietario === null) {
            return;
        }

        $inicio = Carbon::parse($fecha.' '.$hora);

        $conflicto = Cita::query()
            ->delPropietario($propietario)
            ->deFecha($fecha)
            ->queBloqueanAgenda()
            ->when($excepto !== null, fn ($query) => $query->whereKeyNot($excepto->getKey()))
            ->get()
            ->first(static fn (Cita $cita): bool => $cita->solapaCon($inicio, $duracion));

        if ($conflicto !== null) {
            throw BusinessException::conflicto(
                __('messages.cita_solapada'),
                ['hora' => [__('messages.cita_solapada')]],
                'CITA_SOLAPADA',
            );
        }
    }

    /**
     * Un cliente no puede tener dos citas simultáneas.
     *
     * @throws BusinessException
     */
    private function validarAgendaDelCliente(Cliente $cliente, string $fecha, string $hora, int $duracion): void
    {
        $inicio = Carbon::parse($fecha.' '.$hora);

        $conflicto = Cita::query()
            ->delCliente($cliente)
            ->deFecha($fecha)
            ->queBloqueanAgenda()
            ->get()
            ->first(static fn (Cita $cita): bool => $cita->solapaCon($inicio, $duracion));

        if ($conflicto !== null) {
            throw BusinessException::conflicto(
                __('messages.cita_solapada'),
                ['hora' => [__('messages.cita_solapada')]],
                'CITA_SOLAPADA_CLIENTE',
            );
        }
    }

    /**
     * Normaliza "9:00" / "09:00:00" a "HH:MM:00".
     */
    private function normalizarHora(string $hora): string
    {
        return Carbon::parse('2000-01-01 '.$hora)->format('H:i:s');
    }

    /**
     * Enlace de videollamada provisional para visitas virtuales.
     */
    private function generarEnlaceVirtual(): string
    {
        return sprintf(
            'https://meet.%s/%s',
            parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'mercadoinmueble.test',
            bin2hex(random_bytes(6)),
        );
    }

    /**
     * Auditoría de las transiciones de la cita.
     */
    private function auditar(Cita $cita, string $evento): void
    {
        try {
            activity()
                ->performedOn($cita)
                ->causedBy(auth()->user())
                ->event($evento)
                ->withProperties(['estado' => $cita->estado->value, 'propiedad_id' => $cita->propiedad_id])
                ->log("cita {$evento}");
        } catch (Throwable) {
            // nunca interrumpe la operación
        }
    }
}
