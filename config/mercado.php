<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Configuración de Mercado Inmueble
|--------------------------------------------------------------------------
|
| Parámetros de negocio de la plataforma: monedas, paginación, medios,
| caché de catálogos, reglas de rate limiting y geolocalización.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | País y moneda por defecto
    |--------------------------------------------------------------------------
    */
    'pais_por_defecto' => env('MERCADO_PAIS', 'Bolivia'),

    'moneda_por_defecto' => env('MERCADO_MONEDA_POR_DEFECTO', 'BOB'),

    /*
    |--------------------------------------------------------------------------
    | Comisión usada por el reporte de ingresos (porcentaje)
    |--------------------------------------------------------------------------
    */
    'comision_porcentaje' => (float) env('MERCADO_COMISION_PORCENTAJE', 3.0),

    /*
    |--------------------------------------------------------------------------
    | Caché de catálogos (amenidades, ciudades, tipos, monedas)
    |--------------------------------------------------------------------------
    */
    'catalogo_cache_segundos' => (int) env('MERCADO_CATALOGO_CACHE_SEGUNDOS', 3600),

    /*
    |--------------------------------------------------------------------------
    | Paginación estándar (?page= &per_page=)
    |--------------------------------------------------------------------------
    */
    'paginacion' => [
        'por_defecto' => (int) env('MERCADO_PAGINACION_POR_DEFECTO', 15),
        'maxima' => (int) env('MERCADO_PAGINACION_MAXIMA', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Archivos y medios
    |--------------------------------------------------------------------------
    | Las fotos se guardan en un disk público y los videos en un disk privado
    | (o S3). Un video por propiedad: se valida en PropiedadService.
    */
    'media' => [
        'disco_fotos' => env('MEDIA_DISCO_FOTOS', 'public'),
        'disco_videos' => env('MEDIA_DISCO_VIDEOS', 'private'),
        'max_fotos_por_propiedad' => (int) env('MEDIA_MAX_FOTOS_POR_PROPIEDAD', 20),
        'max_fotos_por_subida' => (int) env('MEDIA_MAX_FOTOS_POR_SUBIDA', 10),
        'max_peso_foto_kb' => (int) env('MEDIA_MAX_PESO_FOTO_KB', 5120),
        'max_peso_video_kb' => (int) env('MEDIA_MAX_PESO_VIDEO_KB', 102400),
        'mimes_foto' => ['jpg', 'jpeg', 'png', 'webp'],
        'mimes_video' => ['mp4', 'mov', 'm4v', 'webm'],
        'ancho_max_foto' => 1920,
        'ancho_thumbnail' => 400,
        'generar_thumbnails' => true,
        'url_firmada_minutos' => 30, // URLs temporales para el disco privado
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting (peticiones por minuto)
    |--------------------------------------------------------------------------
    | El limitador 'api' resuelve el límite según el rol del usuario.
    */
    'rate_limits' => [
        'invitado' => (int) env('RATE_LIMIT_INVITADO', 60),
        'cliente' => (int) env('RATE_LIMIT_CLIENTE', 120),
        'vendedor' => (int) env('RATE_LIMIT_VENDEDOR', 240),
        'inmobiliaria' => (int) env('RATE_LIMIT_INMOBILIARIA', 480),
        'administrador' => (int) env('RATE_LIMIT_ADMINISTRADOR', 600),
        'auth' => (int) env('RATE_LIMIT_AUTH', 10),
        'contacto' => (int) env('RATE_LIMIT_CONTACTO', 5),
        'docs' => (int) env('RATE_LIMIT_DOCS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Geolocalización (búsqueda por distancia con Haversine)
    |--------------------------------------------------------------------------
    */
    'geo' => [
        'centro_por_defecto' => [
            'latitud' => (float) env('MERCADO_GEO_LATITUD', -16.5),
            'longitud' => (float) env('MERCADO_GEO_LONGITUD', -68.15),
        ],
        'radio_max_km' => (float) env('MERCADO_GEO_RADIO_MAX_KM', 100),
        'radio_por_defecto_km' => 10.0,
        'unidad' => 'km', // km | mi
        'tierra_radio_km' => 6371,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cliente OAuth first-party (usado por /api/v1/auth/refresh)
    |--------------------------------------------------------------------------
    | Se genera automáticamente con: php artisan mercado:instalar
    */
    'oauth' => [
        'client_id' => env('PASSPORT_CLIENT_ID'),
        'client_secret' => env('PASSPORT_CLIENT_SECRET'),
        'personal_access_client_id' => env('PASSPORT_PERSONAL_CLIENT_ID'),
        'token_ttl_minutos' => (int) env('PASSPORT_TOKEN_TTL_MINUTOS', 120),
        'refresh_ttl_dias' => (int) env('PASSPORT_REFRESH_TTL_DIAS', 30),
        'personal_token_ttl_dias' => (int) env('PASSPORT_PERSONAL_TOKEN_TTL_DIAS', 30),

        /*
         * Catálogo de scopes OAuth2. Los clientes first-party (app móvil/SPA)
         * reciben el scope del rol del usuario; los clientes de terceros deben
         * solicitar scopes explícitos.
         */
        'scopes' => [
            'inmobiliaria' => 'Acceso al panel de inmobiliaria',
            'vendedor' => 'Acceso al panel de vendedor',
            'cliente' => 'Acceso al panel de cliente',
            'administrador' => 'Acceso al panel de administración (gestión de cuentas, anunciantes y moderación)',
            'propiedades:leer' => 'Consultar propiedades (incluye datos de contacto)',
            'propiedades:escribir' => 'Crear, actualizar y eliminar propiedades',
            'citas:leer' => 'Consultar la agenda de citas',
            'citas:escribir' => 'Agendar, reprogramar y cancelar citas',
            'mensajes:leer' => 'Leer hilos de mensajes',
            'mensajes:escribir' => 'Responder en los hilos de mensajes',
            'reportes:leer' => 'Consultar reportes y métricas',
            'perfil:escribir' => 'Actualizar el perfil del usuario',
            'admin:usuarios' => 'Gestionar las cuentas de la plataforma',
            'admin:moderacion' => 'Moderar anunciantes y publicaciones',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reglas de negocio del dominio
    |--------------------------------------------------------------------------
    */
    'reglas' => [
        'video_por_propiedad' => 1,
        'foto_principal_obligatoria' => false,
        'max_amenidades_por_propiedad' => 25,
        'estados_propiedad_publicables' => ['borrador', 'pausada', 'publicada'],
        'dias_anticipacion_cita_min' => 0,
        'dias_anticipacion_cita_max' => 90,
        'duracion_cita_minutos' => 30,
        'duracion_cita_max_minutos' => 240,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notificaciones
    |--------------------------------------------------------------------------
    */
    'notificaciones' => [
        'email_admin' => env('MERCADO_EMAIL_ADMIN', 'soporte@mercadoinmueble.com'),
        'encolar' => (bool) env('MERCADO_NOTIFICACIONES_ENCOLAR', true),
        'canal_database' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel de administración (rol `administrador`)
    |--------------------------------------------------------------------------
    | Los agentes de la plataforma gestionan inmobiliarias, vendedores,
    | clientes, publicaciones y los mensajes del formulario de contacto.
    |
    | La cuenta inicial se crea con `php artisan mercado:instalar` (o con
    | `php artisan mercado:crear-admin {correo}`); nunca por el registro
    | público de la API.
    */
    'admin' => [
        // Correo de la cuenta que crea el instalador.
        'email' => env('ADMIN_EMAIL', 'admin@mercadoinmueble.com'),
        'nombre' => env('ADMIN_NOMBRE', 'Administrador Mercado Inmueble'),

        // Sin contraseña explícita el instalador genera una aleatoria y la
        // imprime una única vez (no queda almacenada en texto plano).
        'password' => env('ADMIN_PASSWORD'),

        'crear_en_instalacion' => (bool) env('ADMIN_CREAR_EN_INSTALACION', true),

        // Reglas de protección del propio panel.
        'reglas' => [
            // Siempre debe quedar al menos un administrador activo: impide
            // desactivar, degradar o eliminar la última cuenta con acceso.
            'minimo_administradores_activos' => 1,
            // Al desactivar una cuenta se revocan todos sus tokens.
            'revocar_tokens_al_desactivar' => true,
        ],
    ],

];
