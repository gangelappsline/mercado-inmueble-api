<?php

declare(strict_types=1);

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [

    /*
    |--------------------------------------------------------------------------
    | Rutas documentadas
    |--------------------------------------------------------------------------
    | Toda la API pública versionada vive bajo /api/v1.
    */
    'api_path' => 'api/v1',

    'api_domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Archivo de exportación (php artisan scramble:export)
    |--------------------------------------------------------------------------
    */
    'export_path' => 'openapi.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    //'openapi_version' => \Dedoc\Scramble\OpenApiVersion::V3_1,

    'info' => [
        'version' => env('APP_VERSION', '1.0.0'),
        'title' => 'Mercado Inmueble API',
        'description' => <<<'MD'
        API REST de la plataforma inmobiliaria **Mercado Inmueble**.

        ### Autenticación
        Usa `POST /api/v1/auth/login` para obtener `access_token` y `refresh_token`
        (OAuth2, Laravel Passport). Envía el access token en cada petición:

        ```
        Authorization: Bearer {access_token}
        ```

        Cuando el access token expire usa `POST /api/v1/auth/refresh` con el
        `refresh_token` para obtener un par nuevo (rotación de refresh tokens).

        ### Roles
        La API expone cuatro paneles: `/api/v1/inmobiliaria/*`, `/api/v1/vendedor/*`,
        `/api/v1/cliente/*` y `/api/v1/admin/*`. Cada grupo exige el rol
        correspondiente mediante el middleware `role:{rol}`.

        El rol `administrador` opera la plataforma: gestiona las cuentas (altas,
        suspensiones y cambios de rol), verifica inmobiliarias y vendedores,
        modera cualquier publicación, atiende los mensajes de contacto y
        consulta la bitácora de auditoría. Sus cuentas no se crean por
        `POST /auth/register/{rol}`: usa `php artisan mercado:crear-admin {correo}`
        o `POST /api/v1/admin/usuarios`.

        ### Errores
        Formato uniforme:
        ```json
        {"success": false, "message": "...", "code": "VALIDATION_ERROR", "errors": {}}
        ```

        ### Rate limiting
        El límite por minuto depende del rol: invitado 60, cliente 120,
        vendedor 240, inmobiliaria 480, administrador 600. Las cabeceras
        `X-RateLimit-*` informan el consumo restante.
        MD,
    ],

    'dev_tools' => [
        'enabled' => (bool) env('SCRAMBLE_DEV_TOOLS', env('APP_DEBUG', false)),
    ],

    'renderer' => 'elements',

    'renderers' => [
        'elements' => [
            'view' => 'scramble::docs',
            'theme' => 'light',
            'hideTryIt' => false,
            'hideSchemas' => false,
            'logo' => '',
            'tryItCredentialsPolicy' => 'include',
            'layout' => 'responsive',
            'router' => 'hash',
        ],
    ],

    'servers' => null,

    'enum_cases_description_strategy' => 'description',

    'enum_cases_names_strategy' => false,

    'flatten_deep_query_parameters' => true,

    /*
    |--------------------------------------------------------------------------
    | Middleware de la documentación
    |--------------------------------------------------------------------------
    | En local es abierta; en otros entornos requiere el gate 'viewApiDocs'.
    */
    'middleware' => [
        'web',
        'throttle:docs',
        RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    /*
    |--------------------------------------------------------------------------
    | Seguridad documentada automáticamente (Bearer token)
    |--------------------------------------------------------------------------
    */
    'security_strategy' => [
        \Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy::class,
        [
            'middleware' => ['auth', 'auth:*'],
            'scheme' => \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer', 'JWT'),
        ],
    ],

];
