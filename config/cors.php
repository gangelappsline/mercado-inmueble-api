<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Rutas protegidas por CORS
    |--------------------------------------------------------------------------
    */
    'paths' => ['api/*', 'oauth/*', 'docs/*', 'storage/*'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Orígenes permitidos
    |--------------------------------------------------------------------------
    | Define CORS_ALLOWED_ORIGINS como lista separada por comas en producción.
    */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
        'X-Request-Id',
    ],

    'max_age' => 3600,

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
