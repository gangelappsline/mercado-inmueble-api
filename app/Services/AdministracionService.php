<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoPropiedad;
use App\Enums\Role;
use App\Exceptions\BusinessException;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\Favorito;
use App\Models\Inmobiliaria;
use App\Models\Interes;
use App\Models\Mensaje;
use App\Models\Propiedad;
use App\Models\User;
use App\Models\Vendedor;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Casos de uso del panel de administración (rol `administrador`).
 *
 * Los agentes de la plataforma operan sobre el resto de los roles: altas y
 * bajas de cuentas, verificación de inmobiliarias y vendedores, moderación del
 * catálogo, bandeja de contacto y métricas globales.
 *
 * Reglas transversales:
 *  - Siempre debe quedar al menos `mercado.admin.reglas.minimo_administradores_activos`
 *    administradores activos: no se puede suspender, degradar ni dar de baja el
 *    último (la policy ya impide que un agente se aplique estas acciones a sí mismo).
 *  - Toda acción administrativa se audita en el `activity_log` con el agente
 *    como `causer`.
 *  - Al suspender una cuenta o cambiar su rol se revocan sus tokens: los scopes
 *    OAuth emitidos dejan de ser válidos de inmediato.
 */
final class AdministracionService
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly PropiedadService $propiedades,
        private readonly NotificacionService $notificaciones,
        private readonly PropiedadRepositoryInterface $repositorio,
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────
    // Métricas globales
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Resumen ejecutivo de la plataforma para el dashboard de administración.
     *
     * @return array<string, mixed>
     */
    public function resumen(): array
    {
        $desde = Carbon::now()->subDays(30);

        return [
            'usuarios' => [
                'total' => User::query()->count(),
                'activos' => User::query()->activos()->count(),
                'inactivos' => User::query()->where('is_active', false)->count(),
                'nuevos_30_dias' => User::query()->where('created_at', '>=', $desde)->count(),
                'por_rol' => $this->usuariosPorRol(),
            ],
            'inmobiliarias' => [
                'total' => Inmobiliaria::query()->count(),
                'verificadas' => Inmobiliaria::query()->where('verificado', true)->count(),
                'pendientes_de_verificacion' => Inmobiliaria::query()->where('verificado', false)->count(),
            ],
            'vendedores' => [
                'total' => Vendedor::query()->count(),
                'verificados' => Vendedor::query()->verificados()->count(),
                'pendientes_de_verificacion' => Vendedor::query()->where('verificado', false)->count(),
            ],
            'clientes' => [
                'total' => Cliente::query()->count(),
            ],
            'propiedades' => $this->repositorio->resumenGlobalPorEstado(),
            'actividad' => $this->actividadComercial($desde),
            'contactos' => [
                'total' => Contacto::query()->count(),
                'pendientes' => Contacto::query()->noAtendidos()->count(),
            ],
            'ingresos' => $this->ingresosEstimados(),
            'administradores_activos' => $this->administradoresActivos(),
            'generado_en' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Cuentas agrupadas por rol (siempre con todos los roles del catálogo).
     *
     * @return array<string, int>
     */
    private function usuariosPorRol(): array
    {
        $conteos = User::query()
            ->selectRaw('role, COUNT(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->all();

        $porRol = [];

        foreach (Role::cases() as $rol) {
            $porRol[$rol->value] = (int) ($conteos[$rol->value] ?? 0);
        }

        return $porRol;
    }

    /**
     * Volumen comercial de los últimos 30 días.
     *
     * @return array<string, int>
     */
    private function actividadComercial(Carbon $desde): array
    {
        return [
            'vistas' => (int) Propiedad::query()->sum('vistas_count'),
            'intereses' => Interes::query()->where('created_at', '>=', $desde)->count(),
            'citas' => Cita::query()->where('created_at', '>=', $desde)->count(),
            'mensajes' => Mensaje::query()->where('created_at', '>=', $desde)->count(),
            'favoritos' => Favorito::query()->where('created_at', '>=', $desde)->count(),
        ];
    }

    /**
     * Operaciones cerradas y comisión estimada, agrupadas por moneda.
     *
     * @return array<string, mixed>
     */
    private function ingresosEstimados(): array
    {
        $comision = (float) config('mercado.comision_porcentaje', 0);

        $filas = Propiedad::query()
            ->whereIn('estado', [EstadoPropiedad::Vendida->value, EstadoPropiedad::Alquilada->value])
            ->selectRaw('moneda, COUNT(*) as operaciones, SUM(precio) as volumen')
            ->groupBy('moneda')
            ->get();

        return [
            'comision_porcentaje' => $comision,
            'por_moneda' => $filas->map(static fn (Model $fila): array => [
                'moneda' => (string) $fila->getAttribute('moneda'),
                'operaciones' => (int) $fila->getAttribute('operaciones'),
                'volumen' => round((float) $fila->getAttribute('volumen'), 2),
                'comision_estimada' => round((float) $fila->getAttribute('volumen') * $comision / 100, 2),
            ])->values()->all(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cuentas de usuario
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Listado global de cuentas con filtros de administración.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, User>
     */
    public function listarUsuarios(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        $busqueda = $this->cadena($filtros['q'] ?? null);

        return User::query()
            ->with('perfil')
            ->when($busqueda !== null, static fn (Builder $query) => $query->where(
                static function (Builder $query) use ($busqueda): void {
                    $comodin = '%'.$busqueda.'%';
                    $query->where('name', 'like', $comodin)
                        ->orWhere('email', 'like', $comodin)
                        ->orWhere('phone', 'like', $comodin);
                },
            ))
            ->when(filled($filtros['role'] ?? null), static fn (Builder $query) => $query->deRol((string) $filtros['role']))
            ->when(isset($filtros['activo']), static fn (Builder $query) => $query->where(
                'is_active',
                filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN),
            ))
            ->when(filled($filtros['rol_excluido'] ?? null), static fn (Builder $query) => $query->where(
                'role',
                '!=',
                (string) $filtros['rol_excluido'],
            ))
            ->orderByDesc('created_at')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Crea una cuenta de cualquier rol (incluido `administrador`).
     *
     * Reutiliza el alta del registro público para los roles con perfil
     * extendido, de modo que las reglas de negocio y la validación sean las
     * mismas; la diferencia es que no se emiten tokens para el agente.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws BusinessException
     */
    public function crearUsuario(Role $rol, array $datos): User
    {
        $usuario = $this->auth->crearCuenta($rol, $datos);
        $usuario->loadMissing('perfil');

        $this->notificaciones->bienvenida($usuario);
        $this->auditar($usuario, 'creada_por_administrador', [
            'role' => $rol->value,
            'email' => $usuario->email,
        ]);

        return $usuario;
    }

    /**
     * Actualiza los datos de contacto y la contraseña de una cuenta.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizarUsuario(User $objetivo, array $datos): User
    {
        $objetivo->update($datos);

        // El cambio de contraseña invalida las sesiones existentes.
        if (array_key_exists('password', $datos)) {
            $objetivo->tokens()->delete();
        }

        $this->auditar($objetivo, 'actualizada_por_administrador', ['campos' => array_keys($datos)]);

        return $objetivo->refresh()->loadMissing('perfil');
    }

    /**
     * Activa o suspende una cuenta (al suspenderla revoca todas sus sesiones).
     *
     * @throws BusinessException 409 si dejaría la plataforma sin administradores.
     */
    public function cambiarEstado(User $objetivo, bool $activo): User
    {
        if (! $activo) {
            $this->protegerUltimoAdministrador($objetivo);
        }

        DB::transaction(function () use ($objetivo, $activo): void {
            $objetivo->forceFill(['is_active' => $activo])->save();

            if (! $activo && (bool) config('mercado.admin.reglas.revocar_tokens_al_desactivar', true)) {
                $objetivo->tokens()->delete();
            }
        });

        $this->auditar($objetivo, $activo ? 'cuenta_activada' : 'cuenta_suspendida');

        return $objetivo->refresh()->loadMissing('perfil');
    }

    /**
     * Reasigna el rol de una cuenta y revoca sus tokens (el scope cambia).
     *
     * @throws BusinessException 409 sin perfil compatible o sin administradores.
     */
    public function cambiarRol(User $objetivo, Role $rol): User
    {
        if ($objetivo->role === $rol) {
            throw new BusinessException(__('messages.admin_rol_sin_cambio'), [], 409, 'ROL_SIN_CAMBIO');
        }

        if ($objetivo->esAdministrador() && ! $rol->esStaff()) {
            $this->protegerUltimoAdministrador($objetivo);
        }

        if (! $objetivo->perfilCoincideCon($rol)) {
            throw new BusinessException(
                __('messages.admin_rol_sin_perfil'),
                ['role' => [__('messages.admin_rol_sin_perfil')]],
                409,
                'PERFIL_INCOMPATIBLE',
                ['perfil_actual' => $objetivo->perfil_type, 'rol_solicitado' => $rol->value],
            );
        }

        $anterior = $objetivo->role;

        DB::transaction(function () use ($objetivo, $rol): void {
            $objetivo->forceFill(['role' => $rol])->save();
            $objetivo->tokens()->delete();
        });

        $this->auditar($objetivo, 'rol_actualizado', [
            'anterior' => $anterior->value,
            'nuevo' => $rol->value,
        ]);

        return $objetivo->refresh()->loadMissing('perfil');
    }

    /**
     * Baja lógica de la cuenta (conserva la trazabilidad y sus registros).
     *
     * @throws BusinessException 409 si es el último administrador activo.
     */
    public function eliminarUsuario(User $objetivo): void
    {
        $this->protegerUltimoAdministrador($objetivo);

        DB::transaction(function () use ($objetivo): void {
            $objetivo->tokens()->delete();
            $objetivo->delete();
        });

        $this->auditar($objetivo, 'cuenta_dada_de_baja');
    }

    /**
     * Restaura una cuenta dada de baja.
     */
    public function restaurarUsuario(User $objetivo): User
    {
        $objetivo->restore();

        $this->auditar($objetivo, 'cuenta_restaurada');

        return $objetivo->refresh()->loadMissing('perfil');
    }

    /**
     * Administradores activos (no puede quedar ninguno por debajo del mínimo
     * configurado en `mercado.admin.reglas`).
     */
    public function administradoresActivos(): int
    {
        return User::query()->administradores()->activos()->count();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Anunciantes (inmobiliarias y vendedores)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Listado administrativo de inmobiliarias.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Inmobiliaria>
     */
    public function listarInmobiliarias(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        return Inmobiliaria::query()
            ->with('user:id,name,email,phone,role,is_active,last_login_at')
            ->withCount('propiedades')
            ->buscar($this->cadena($filtros['q'] ?? null))
            ->when(filled($filtros['ciudad'] ?? null), static fn (Builder $query) => $query->where('ciudad', (string) $filtros['ciudad']))
            ->when(isset($filtros['verificado']), static fn (Builder $query) => $query->where(
                'verificado',
                filter_var($filtros['verificado'], FILTER_VALIDATE_BOOLEAN),
            ))
            ->when(isset($filtros['activo']), function (Builder $query) use ($filtros): void {
                $activo = filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN);
                $query->whereHas('user', static fn (Builder $usuarios) => $usuarios->where('is_active', $activo));
            })
            ->orderByDesc('verificado')
            ->orderByDesc('created_at')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Listado administrativo de vendedores.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Vendedor>
     */
    public function listarVendedores(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        return Vendedor::query()
            ->with('user:id,name,email,phone,role,is_active,last_login_at')
            ->withCount('propiedades')
            ->buscar($this->cadena($filtros['q'] ?? null))
            ->when(filled($filtros['ciudad'] ?? null), static fn (Builder $query) => $query->where('ciudad', (string) $filtros['ciudad']))
            ->when(isset($filtros['verificado']), static fn (Builder $query) => $query->where(
                'verificado',
                filter_var($filtros['verificado'], FILTER_VALIDATE_BOOLEAN),
            ))
            ->when(isset($filtros['activo']), function (Builder $query) use ($filtros): void {
                $activo = filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN);
                $query->whereHas('user', static fn (Builder $usuarios) => $usuarios->where('is_active', $activo));
            })
            ->orderByDesc('verificado')
            ->orderByDesc('created_at')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Otorga o retira la verificación de una inmobiliaria.
     */
    public function verificarInmobiliaria(Inmobiliaria $inmobiliaria, bool $verificado): Inmobiliaria
    {
        $inmobiliaria->forceFill(['verificado' => $verificado])->save();

        $this->auditar($inmobiliaria, $verificado ? 'inmobiliaria_verificada' : 'verificacion_retirada');

        return $inmobiliaria->refresh()->loadMissing('user');
    }

    /**
     * Otorga o retira la verificación de un vendedor.
     */
    public function verificarVendedor(Vendedor $vendedor, bool $verificado): Vendedor
    {
        $vendedor->forceFill(['verificado' => $verificado])->save();

        $this->auditar($vendedor, $verificado ? 'vendedor_verificado' : 'verificacion_retirada');

        return $vendedor->refresh()->loadMissing('user');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Moderación de publicaciones
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Listado global de publicaciones para moderación.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Propiedad>
     */
    public function listarPropiedades(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        return $this->repositorio->listarParaAdministracion($filtros, $porPagina);
    }

    /**
     * Resumen global del catálogo por estado (incluye borradores y rechazadas).
     *
     * @return array<string, int>
     */
    public function resumenPorEstado(): array
    {
        return $this->repositorio->resumenGlobalPorEstado();
    }

    /**
     * Destaca (o retira el destacado de) cualquier publicación.
     */
    public function destacar(Propiedad $propiedad, bool $destacada): Propiedad
    {
        $propiedad->forceFill(['destacada' => $destacada])->save();

        $this->auditar($propiedad, $destacada ? 'destacada_por_administrador' : 'destacado_retirado');

        return $propiedad->refresh()->loadMissing('propietario');
    }

    /**
     * Publica una publicación retenida (valida los requisitos mínimos).
     *
     * @throws BusinessException 422 cuando faltan datos obligatorios.
     */
    public function publicar(Propiedad $propiedad): Propiedad
    {
        return $this->propiedades->publicar($propiedad)->loadMissing('propietario');
    }

    /**
     * Retira una publicación del catálogo sin borrarla.
     */
    public function pausar(Propiedad $propiedad): Propiedad
    {
        return $this->propiedades->pausar($propiedad)->loadMissing('propietario');
    }

    /**
     * Rechaza una publicación por incumplir las políticas del marketplace.
     *
     * @throws BusinessException
     */
    public function rechazar(Propiedad $propiedad, ?string $motivo = null): Propiedad
    {
        if ($propiedad->estado === EstadoPropiedad::Rechazada) {
            throw new BusinessException(
                __('messages.admin_propiedad_ya_rechazada'),
                [],
                409,
                'PROPIEDAD_YA_RECHAZADA',
            );
        }

        $propiedad->forceFill([
            'estado' => EstadoPropiedad::Rechazada,
            'destacada' => false,
        ])->save();

        $this->auditar($propiedad, 'propiedad_rechazada', array_filter([
            'motivo' => $motivo,
        ]));

        return $propiedad->refresh()->loadMissing('propietario');
    }

    /**
     * Baja administrativa de la publicación (elimina también sus medios).
     */
    public function eliminarPropiedad(Propiedad $propiedad): void
    {
        $this->propiedades->eliminar($propiedad);

        $this->auditar($propiedad, 'propiedad_eliminada_por_administrador');
    }

    /**
     * Restaura una publicación dada de baja.
     */
    public function restaurarPropiedad(Propiedad $propiedad): Propiedad
    {
        $propiedad->restore();

        $this->auditar($propiedad, 'propiedad_restaurada');

        return $propiedad->refresh()->loadMissing('propietario');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Bandeja de contacto y auditoría
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Mensajes del formulario público de contacto.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Contacto>
     */
    public function listarContactos(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        $busqueda = $this->cadena($filtros['q'] ?? null);

        return Contacto::query()
            ->when($busqueda !== null, static fn (Builder $query) => $query->where(
                static function (Builder $query) use ($busqueda): void {
                    $comodin = '%'.$busqueda.'%';
                    $query->where('nombre', 'like', $comodin)
                        ->orWhere('email', 'like', $comodin)
                        ->orWhere('asunto', 'like', $comodin);
                },
            ))
            ->when(isset($filtros['atendido']), static fn (Builder $query) => $query->where(
                'atendido',
                filter_var($filtros['atendido'], FILTER_VALIDATE_BOOLEAN),
            ))
            ->orderByDesc('atendido')
            ->orderByDesc('created_at')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Marca un mensaje de contacto como atendido.
     */
    public function atenderContacto(Contacto $contacto): Contacto
    {
        if ($contacto->atendido) {
            return $contacto;
        }

        $contacto->marcarAtendido();

        $this->auditar($contacto, 'contacto_atendido');

        return $contacto->refresh();
    }

    /**
     * Elimina un mensaje de contacto de la bandeja.
     */
    public function eliminarContacto(Contacto $contacto): void
    {
        $this->auditar($contacto, 'contacto_eliminado');

        $contacto->delete();
    }

    /**
     * Registros del `activity_log` con filtros de auditoría.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Model>
     */
    public function listarActividad(array $filtros, int $porPagina = 15): LengthAwarePaginator
    {
        /** @var class-string<Model> $modelo */
        $modelo = (string) config('activitylog.activity_model');

        return $modelo::query()
            ->with('causer')
            ->when(filled($filtros['log_name'] ?? null), static fn (Builder $query) => $query->where('log_name', (string) $filtros['log_name']))
            ->when(filled($filtros['event'] ?? null), static fn (Builder $query) => $query->where('event', (string) $filtros['event']))
            ->when(filled($filtros['subject_type'] ?? null), static fn (Builder $query) => $query->where('subject_type', (string) $filtros['subject_type']))
            ->when(filled($filtros['subject_id'] ?? null), static fn (Builder $query) => $query->where('subject_id', (int) $filtros['subject_id']))
            ->when(filled($filtros['causer_id'] ?? null), static fn (Builder $query) => $query->where('causer_id', (int) $filtros['causer_id']))
            ->when($this->cadena($filtros['q'] ?? null) !== null, static fn (Builder $query) => $query->where(
                'description',
                'like',
                '%'.$filtros['q'].'%',
            ))
            ->when(filled($filtros['desde'] ?? null), static fn (Builder $query) => $query->where('created_at', '>=', (string) $filtros['desde']))
            ->when(filled($filtros['hasta'] ?? null), static fn (Builder $query) => $query->where('created_at', '<=', (string) $filtros['hasta']))
            ->orderByDesc('created_at')
            ->paginate($porPagina)
            ->withQueryString();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Guardas y utilidades internas
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Impide dejar la plataforma sin administradores activos.
     *
     * @throws BusinessException 409
     */
    private function protegerUltimoAdministrador(User $objetivo): void
    {
        if (! $objetivo->esAdministrador() || ! $objetivo->is_active) {
            return;
        }

        $minimo = (int) config('mercado.admin.reglas.minimo_administradores_activos', 1);

        if ($this->administradoresActivos() > $minimo) {
            return;
        }

        throw new BusinessException(
            __('messages.admin_ultimo_administrador'),
            [],
            409,
            'ULTIMO_ADMINISTRADOR',
            ['administradores_activos' => $this->administradoresActivos()],
        );
    }

    /**
     * Auditoría de la acción administrativa (nunca interrumpe el flujo).
     *
     * @param  array<string, mixed>  $propiedades
     */
    private function auditar(Model $sujeto, string $evento, array $propiedades = []): void
    {
        try {
            activity()
                ->performedOn($sujeto)
                ->causedBy(auth()->user())
                ->withProperties($propiedades)
                ->event($evento)
                ->log("administración: {$evento}");
        } catch (Throwable) {
            // La auditoría no debe romper la operación administrativa.
        }
    }

    /**
     * @param  mixed  $valor
     */
    private function cadena(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }
}
