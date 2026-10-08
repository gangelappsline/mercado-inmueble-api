<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\Hilo;
use App\Models\Inmobiliaria;
use App\Models\Interes;
use App\Models\Propiedad;
use App\Models\Reporte;
use App\Models\User;
use App\Models\Vendedor;
use App\Policies\CitaPolicy;
use App\Policies\ClientePolicy;
use App\Policies\ContactoPolicy;
use App\Policies\HiloPolicy;
use App\Policies\InmobiliariaPolicy;
use App\Policies\InteresPolicy;
use App\Policies\PropiedadPolicy;
use App\Policies\ReportePolicy;
use App\Policies\UsuarioPolicy;
use App\Policies\VendedorPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Registra policies y gates de la aplicación.
 *
 * El panel de administración (rol `administrador`) se autoriza con
 * `UsuarioPolicy`, `ContactoPolicy` y las abilities `gestionar`/`verificar`/
 * `moderar` de las policies de cada recurso.
 */
final class AuthServiceProvider extends ServiceProvider
{
    /**
     * Mapa modelo → policy. Se registra explícitamente para que la autorización
     * quede documentada en un único lugar (y no dependa de la convención).
     *
     * @var array<class-string, class-string>
     */
    private const POLICIES = [
        Propiedad::class => PropiedadPolicy::class,
        Interes::class => InteresPolicy::class,
        Cita::class => CitaPolicy::class,
        Hilo::class => HiloPolicy::class,
        Reporte::class => ReportePolicy::class,
        Inmobiliaria::class => InmobiliariaPolicy::class,
        Vendedor::class => VendedorPolicy::class,
        Cliente::class => ClientePolicy::class,
        Contacto::class => ContactoPolicy::class,
        User::class => UsuarioPolicy::class,
    ];

    /**
     * Arranca el registro de autorización.
     */
    public function boot(): void
    {
        foreach (self::POLICIES as $modelo => $policy) {
            Gate::policy($modelo, $policy);
        }

        $this->registrarGates();
    }

    /**
     * Gates de acceso global (documentación y panel interno).
     */
    private function registrarGates(): void
    {
        // Acceso a /docs/api fuera de local: sólo correos e IPs autorizados.
        Gate::define('viewApiDocs', static function (?User $user = null): bool {
            $correos = array_filter(array_map(
                'trim',
                explode(',', (string) env('DOCS_ALLOWED_EMAILS', ''))
            ));

            if ($user !== null && $correos !== [] && in_array(Str::lower($user->email), array_map('strtolower', $correos), true)) {
                return true;
            }

            $ips = array_filter(array_map('trim', explode(',', (string) env('DOCS_ALLOWED_IPS', ''))));

            return $ips !== [] && in_array(request()->ip(), $ips, true);
        });
    }
}
