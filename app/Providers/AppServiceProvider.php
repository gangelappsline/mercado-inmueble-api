<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Role;
use App\Models\Cliente;
use App\Models\Inmobiliaria;
use App\Models\User;
use App\Models\Vendedor;
use App\Repositories\Contracts\PropiedadRepositoryInterface;
use App\Repositories\PropiedadRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

/**
 * Configuración transversal: mapa polimórfico, Passport, rate limiting por rol,
 * localización y reglas de contraseña.
 */
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra bindings del contenedor (repositorios de consultas complejas).
     */
    public function register(): void
    {
        $this->app->bind(PropiedadRepositoryInterface::class, PropiedadRepository::class);
    }

    /**
     * Arranca la configuración de la aplicación.
     */
    public function boot(): void
    {
        $this->configurarElocuent();
        $this->configurarPassport();
        $this->configurarRateLimiting();
        $this->configurarLocalizacion();
        $this->configurarReglasDeSeguridad();
    }

    /**
     * Mapa polimórfico estable (evita guardar FQCN en la base de datos) y
     * protección contra asignación silenciosa de atributos no fillable.
     */
    private function configurarElocuent(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'inmobiliaria' => Inmobiliaria::class,
            'vendedor' => Vendedor::class,
            'cliente' => Cliente::class,
        ]);

        if (! $this->app->isProduction()) {
            Model::preventSilentlyDiscardingAttributes();
        }
    }

    /**
     * Vigencias de tokens, scopes y grants de Passport.
     */
    private function configurarPassport(): void
    {
        $oauth = config('mercado.oauth');

        Passport::tokensCan($oauth['scopes']);

        Passport::tokensExpireIn(
            Carbon::now()->addMinutes((int) $oauth['token_ttl_minutos'])
        );

        Passport::refreshTokensExpireIn(
            Carbon::now()->addDays((int) $oauth['refresh_ttl_dias'])
        );

        Passport::personalAccessTokensExpireIn(
            Carbon::now()->addDays((int) $oauth['personal_token_ttl_dias'])
        );

        // El login de la API usa el grant `password` porque es el único flujo
        // de Passport que emite refresh tokens (los personales no los emiten).
        Passport::enablePasswordGrant();
    }

    /**
     * Limitadores: el límite de la API depende del rol autenticado.
     */
    private function configurarRateLimiting(): void
    {
        $limites = config('mercado.rate_limits');

        // Límite principal de la API (grupo `api` → throttle:api).
        RateLimiter::for('api', static function (Request $request) use ($limites): Limit {
            $porMinuto = match ($request->user()?->role) {
                Role::Inmobiliaria => $limites['inmobiliaria'],
                Role::Vendedor => $limites['vendedor'],
                Role::Cliente => $limites['cliente'],
                default => $limites['invitado'],
            };

            return Limit::perMinute((int) $porMinuto)
                ->by($request->user()?->getAuthIdentifier() ?: 'ip:'.$request->ip())
                ->response(static fn (Request $request, array $headers) => \App\Support\ApiResponse::error(
                    __('messages.demasiadas_peticiones'),
                    429,
                    'TOO_MANY_REQUESTS',
                    [],
                    ['retry_after' => $headers['Retry-After'] ?? null],
                ));
        });

        // Login, registro y recuperación de contraseña.
        RateLimiter::for('auth', static fn (Request $request): Limit => Limit::perMinute((int) $limites['auth'])
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        // Formulario público de contacto (anti-spam).
        RateLimiter::for('contacto', static fn (Request $request): Limit => Limit::perMinute((int) $limites['contacto'])
            ->by($request->ip()));

        // Documentación OpenAPI.
        RateLimiter::for('docs', static fn (Request $request): Limit => Limit::perMinute((int) $limites['docs'])
            ->by($request->user()?->getAuthIdentifier() ?: 'ip:'.$request->ip()));
    }

    /**
     * Español por defecto en fechas y reglas de contraseña seguras.
     */
    private function configurarLocalizacion(): void
    {
        Carbon::setLocale(config('app.locale', 'es'));

        Password::defaults(static fn (): Password => app()->isProduction()
            ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
            : Password::min(8)->letters()->numbers());
    }

    /**
     * Fuerza HTTPS en entornos detrás de balanceador y evita fugas de datos.
     */
    private function configurarReglasDeSeguridad(): void
    {
        if ((bool) env('APP_FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }
    }
}
