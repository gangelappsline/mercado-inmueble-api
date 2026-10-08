<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

/**
 * Cuenta de acceso a la plataforma. Cada usuario tiene un rol y un perfil
 * extendido asociado polimórficamente (`perfil`): Inmobiliaria, Vendedor o
 * Cliente. Las cuentas con rol `administrador` no tienen perfil extendido
 * (`perfil_type`/`perfil_id` quedan en null): operan el panel de gestión.
 *
 * @property Role $role
 * @property-read Inmobiliaria|Vendedor|Cliente|null $perfil
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable;
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * Atributos asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar',
        'is_active',
    ];

    /**
     * Atributos ocultos en las respuestas.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Atributos auditados (nunca la contraseña).
     *
     * @return array<int, string>
     */
    protected function atributosAuditables(): array
    {
        return ['name', 'email', 'role', 'phone', 'avatar', 'is_active'];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Relaciones
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Perfil extendido polimórfico (Inmobiliaria | Vendedor | Cliente).
     *
     * @return MorphTo<Model, $this>
     */
    public function perfil(): MorphTo
    {
        return $this->morphTo('perfil', 'perfil_type', 'perfil_id');
    }

    /**
     * Perfil de inmobiliaria.
     *
     * @return HasOne<Inmobiliaria, $this>
     */
    public function inmobiliaria(): HasOne
    {
        return $this->hasOne(Inmobiliaria::class);
    }

    /**
     * Perfil de vendedor.
     *
     * @return HasOne<Vendedor, $this>
     */
    public function vendedor(): HasOne
    {
        return $this->hasOne(Vendedor::class);
    }

    /**
     * Perfil de cliente.
     *
     * @return HasOne<Cliente, $this>
     */
    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class);
    }

    /**
     * Mensajes escritos por el usuario en cualquier hilo.
     *
     * @return HasMany<Mensaje, $this>
     */
    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Consultas (scopes)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeDeRol(Builder $query, Role|string $rol): Builder
    {
        return $query->where('role', $rol instanceof Role ? $rol->value : $rol);
    }

    /**
     * Cuentas del equipo de administración (agentes y administradores).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeAdministradores(Builder $query): Builder
    {
        return $query->deRol(Role::Administrador);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Comportamiento
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Verifica si el usuario tiene exactamente el rol indicado.
     */
    public function hasRole(Role|string $rol): bool
    {
        return $this->role->value === ($rol instanceof Role ? $rol->value : $rol);
    }

    /**
     * Verifica si el usuario tiene alguno de los roles indicados
     * (usado por el middleware `role:...`).
     *
     * @param  array<int, Role|string>|Role|string  $roles
     */
    public function hasAnyRole(array|Role|string $roles): bool
    {
        $roles = is_array($roles) ? $roles : func_get_args();

        foreach ($roles as $rol) {
            if ($this->hasRole($rol)) {
                return true;
            }
        }

        return false;
    }

    public function esInmobiliaria(): bool
    {
        return $this->role === Role::Inmobiliaria;
    }

    public function esVendedor(): bool
    {
        return $this->role === Role::Vendedor;
    }

    public function esCliente(): bool
    {
        return $this->role === Role::Cliente;
    }

    /**
     * ¿La cuenta pertenece al equipo de administración de la plataforma?
     */
    public function esAdministrador(): bool
    {
        return $this->role === Role::Administrador;
    }

    /**
     * ¿La cuenta es un anunciante (inmobiliaria o vendedor)?
     */
    public function esAnunciante(): bool
    {
        return $this->role->esAnunciante();
    }

    /**
     * ¿El perfil extendido vinculado corresponde al rol indicado?
     *
     * Se usa al reasignar roles desde el panel de administración: un usuario
     * sólo puede cambiar a un rol cuyo perfil extendido ya posee (o a
     * `administrador`, que no requiere perfil).
     */
    public function perfilCoincideCon(Role $rol): bool
    {
        $perfil = $this->relationLoaded('perfil') ? $this->perfil : $this->perfil()->first();

        return match ($rol) {
            Role::Administrador => true,
            Role::Inmobiliaria => $perfil instanceof Inmobiliaria,
            Role::Vendedor => $perfil instanceof Vendedor,
            Role::Cliente => $perfil instanceof Cliente,
        };
    }

    /**
     * Verifica si el rol puede publicar propiedades.
     */
    public function publicaPropiedades(): bool
    {
        return $this->role->publicaPropiedades();
    }

    /**
     * Perfil que actúa como propietario de propiedades
     * (null para clientes y administradores).
     */
    public function propietario(): Inmobiliaria|Vendedor|null
    {
        $perfil = $this->relationLoaded('perfil') ? $this->perfil : $this->perfil()->first();

        return $perfil instanceof Inmobiliaria || $perfil instanceof Vendedor ? $perfil : null;
    }

    /**
     * Perfil de cliente (null si el usuario no es cliente).
     */
    public function perfilCliente(): ?Cliente
    {
        $perfil = $this->relationLoaded('perfil') ? $this->perfil : $this->perfil()->first();

        return $perfil instanceof Cliente ? $perfil : null;
    }

    /**
     * Nombre para mostrar del usuario o de su perfil extendido.
     */
    public function nombreVisible(): string
    {
        $perfil = $this->relationLoaded('perfil') ? $this->perfil : $this->perfil()->first();

        return match (true) {
            $perfil instanceof Inmobiliaria => $perfil->nombreComercial(),
            $perfil instanceof Vendedor => $perfil->nombreCompleto(),
            default => $this->name,
        };
    }

    /**
     * Vincula el perfil extendido del usuario (relación polimórfica 1:1).
     * Es el único punto autorizado para escribir perfil_type/perfil_id.
     */
    public function vincularPerfil(Inmobiliaria|Vendedor|Cliente $perfil): void
    {
        $this->forceFill([
            'perfil_type' => $perfil->getMorphClass(),
            'perfil_id' => $perfil->getKey(),
        ])->saveQuietly();

        $this->setRelation('perfil', $perfil);
    }

    /**
     * Registra el último acceso exitoso.
     */
    public function registrarAcceso(): void
    {
        $this->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}
