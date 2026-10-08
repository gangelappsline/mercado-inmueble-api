# Mercado Inmueble API

API REST (API-only) de la plataforma inmobiliaria **Mercado Inmueble**: catálogo público de
propiedades, paneles para **inmobiliarias**, **vendedores** y **clientes**, agenda de visitas,
mensajería entre las partes, reportes y exportaciones.

Construida con **Laravel 12**, **Passport (OAuth2)**, **MySQL 8** y **PHP 8.3+**, con
arquitectura por capas (Controllers → Form Requests → Services → Repositories → Models),
autorización por *policies*, validación estricta, caché de catálogos y prioridad en seguridad.

---

## Índice

1. [Características](#características)
2. [Stack y requisitos](#stack-y-requisitos)
3. [Instalación rápida](#instalación-rápida)
4. [Datos de demostración](#datos-de-demostración)
5. [Arquitectura](#arquitectura)
6. [Autenticación y autorización](#autenticación-y-autorización)
7. [Endpoints](#endpoints)
8. [Archivos y medios](#archivos-y-medios)
9. [Reportes y exportaciones](#reportes-y-exportaciones)
10. [Rate limiting](#rate-limiting)
11. [Comandos artisan](#comandos-artisan)
12. [Pruebas](#pruebas)
13. [Documentación OpenAPI](#documentación-openapi)
14. [Despliegue](#despliegue)
15. [Seguridad](#seguridad)
16. [Estructura del proyecto](#estructura-del-proyecto)

---

## Características

- **Catálogo público** con filtros combinables (tipo, operación, precio, ambientes, área,
  amenidades, cercanía geográfica), orden y paginación.
- **Tres paneles** con sus propios endpoints: `inmobiliaria`, `vendedor` y `cliente`.
- **Publicaciones** con galería de fotos (principal, orden), **un** video por propiedad,
  amenidades del catálogo y publicación/pausa/cierre de operación.
- **Embudo comercial**: intereses (leads) → hilo de mensajes → cita → operación cerrada.
- **Agenda de citas** con validación de ventana temporal, solapamientos por agenda y
  transiciones de estado (pendiente → confirmada/reprogramada → completada/no asistió/cancelada).
- **Mensajería** con adjuntos, contadores de no leídos y cierre/reapertura de hilos.
- **Reportes** (resumen, más vistas, conversión, ingresos) y exportación a **PDF, Excel y CSV**.
- **Emails transaccionales** (9 plantillas HTML + texto plano) y notificaciones en base de datos.
- **Seguridad**: Passport OAuth2, middleware por rol, policies por recurso, rate limiting por rol,
  cabeceras de seguridad, URLs firmadas para archivos privados y auditoría con Activity Log.
- **Rendimiento**: eager loading, índices compuestos, contadores desnormalizados, métricas diarias
  agregadas y caché de catálogos (amenidades, ciudades y opciones de enums).

## Stack y requisitos

| Componente | Versión / notas |
| --- | --- |
| PHP | 8.3 o superior (`pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` recomendado) |
| Laravel | 12.x |
| Base de datos | MySQL 8 (también funciona en SQLite para desarrollo/pruebas) |
| Autenticación | Laravel Passport 13 (OAuth2: `password`, `refresh_token`, tokens personales) |
| Documentación | dedoc/scramble (OpenAPI 3.1 + UI en `/docs/api`) |
| Auditoría | spatie/laravel-activitylog 4.x |
| Colas | `database` en local, Redis/SQS en producción |
| Locale | `es` (mensajes de validación y de negocio en español) |

Requisitos de PHP en `composer.json`: `php ^8.3`, `ext-fileinfo`, `ext-json`, `ext-openssl`.

## Instalación rápida

```bash
# 1. Dependencias
composer install

# 2. Entorno
cp .env.example .env

# 3. Instalación completa (APP_KEY, migraciones, Passport, clientes OAuth, storage)
php artisan mercado:instalar

# 4. Datos de demostración (opcional pero recomendado)
php artisan mercado:instalar --seed
#   o bien: php artisan db:seed

# 5. Servidor local
php artisan serve
```

`mercado:instalar` ejecuta, en orden:

1. `key:generate` si falta `APP_KEY`.
2. `migrate` (o `migrate:fresh` con `--fresh`).
3. `storage:link` (fotos públicas en `storage/app/public`).
4. `passport:keys` si faltan las claves.
5. Creación de los clientes OAuth *first-party* (`password` + `personal_access`) y escritura de
   `PASSPORT_CLIENT_ID`, `PASSPORT_CLIENT_SECRET` y `PASSPORT_PERSONAL_CLIENT_ID` en el `.env`.
6. `optimize:clear`.

Opciones: `--fresh` (recrea la base de datos), `--seed` (datos demo), `--force` (regenera los
clientes OAuth aunque ya existan).

### Variables de entorno principales

```dotenv
APP_NAME="Mercado Inmueble"
APP_URL=http://localhost:8000
APP_LOCALE=es
DB_CONNECTION=mysql
DB_DATABASE=mercado_inmueble

# Cliente OAuth first-party (lo rellena mercado:instalar)
PASSPORT_CLIENT_ID=
PASSPORT_CLIENT_SECRET=
PASSPORT_TOKEN_TTL_MINUTOS=120
PASSPORT_REFRESH_TTL_DIAS=30

# Medios
MEDIA_DISCO_FOTOS=public            # storage/app/public
MEDIA_DISCO_VIDEOS=private          # storage/app/private (usa s3 en producción)
MEDIA_MAX_PESO_FOTO_KB=5120
MEDIA_MAX_PESO_VIDEO_KB=102400

# Negocio
MERCADO_PAIS=Bolivia
MERCADO_MONEDA_POR_DEFECTO=BOB
MERCADO_COMISION_PORCENTAJE=3.0
RATE_LIMIT_INMOBILIARIA=480
```

## Datos de demostración

`php artisan mercado:instalar --seed` siembra:

- **Catálogo**: 39 amenidades agrupadas por categoría.
- **Cuentas**: 5 inmobiliarias, 3 vendedores y 10 clientes → **26 propiedades** con fotos,
  videos, amenidades y contadores de actividad; más intereses, hilos, mensajes, citas,
  favoritos, reportes y contactos.

Todas las cuentas demo comparten la contraseña **`Password123!`**:

| Rol | Correos |
| --- | --- |
| Inmobiliaria | `inmobiliaria1@demo.mercadoinmueble.com` … `inmobiliaria5@demo.mercadoinmueble.com` |
| Vendedor | `vendedor1@demo.mercadoinmueble.com` … `vendedor3@demo.mercadoinmueble.com` |
| Cliente | `cliente1@demo.mercadoinmueble.com` … `cliente10@demo.mercadoinmueble.com` |

> Los archivos de los medios sembrados son **rutas de ejemplo** (no se versionan binarios); al
> subir fotos o videos reales desde la API se procesan y almacenan como corresponde.

## Arquitectura

```
Petición HTTP
   ↓  Middleware: ForceJsonResponse → throttle:{api,auth,contacto} → auth:api → activo → role:...
   ↓  Route model binding + Form Request (validación en español)
Controller  (Api/{Auth,Public,Inmobiliaria,Vendedor,Cliente,Panel})
   ↓  $this->authorize(...)  →  Policy (propiedad/interés/cita/hilo/favorito/reporte/perfil)
   ↓  Service (reglas de negocio, transacciones, eventos)
   ↓  Repository (consultas complejas, filtros, eager loading)
   ↓  Model (Eloquent + ámbitos reutilizables)
JsonResource → App\Support\ApiResponse (sobre uniforme)
```

- **Services**: `PropiedadService`, `MediaService`, `InteresService`, `CitaService`,
  `MensajeService`, `FavoritoService`, `ContactoService`, `ReporteService`,
  `NotificacionService`, `CatalogoService`, `AuthService`.
- **Repositorio**: `PropiedadRepositoryInterface` + `PropiedadRepository` (filtros del catálogo,
  geolocalización Haversine, similares, resumen por estado, ciudades con conteo).
- **Notificaciones**: `NotificacionService` es la única salida de correo (mailables `ShouldQueue`
  en la cola `emails`) y de notificaciones en base de datos.
- **Respuestas**: todas comparten el sobre
  `{success, message, data?, meta?}` / `{success:false, message, code, errors?}`.
- **Auditoría**: `activity_log` registra creación, edición, publicación, pausa, cierre y borrado
  de propiedades y perfiles (log `mercado-inmueble`, retención configurable).

## Autenticación y autorización

- **Passport OAuth2** con grant `password` para los clientes *first-party*
  (`POST /api/v1/auth/login` devuelve `access_token` + `refresh_token`).
- El cliente *first-party* es **confidencial**: siempre se envía `client_id` + `client_secret`
  (configurados por `mercado:instalar`). Los tokens personales se usan como respaldo.
- TTL: access token **120 min**, refresh token **30 días**, tokens personales **30 días**.
- **Scopes** (`inmobiliaria`, `vendedor`, `cliente`, `propiedades:leer|escribir`,
  `citas:leer|escribir`, `mensajes:leer|escribir`, `reportes:leer`, `perfil:escribir`): cada
  usuario recibe el scope de su rol; los clientes de terceros solicitan scopes explícitos.
- **Middleware**: `auth:api` (token válido), `activo` (cuenta habilitada), `role:inmobiliaria|vendedor|cliente`.
- **Policies**: la propiedad sólo la administra su anunciante; intereses, citas y hilos sólo sus
  participantes; los favoritos y el presupuesto sólo el propio cliente.
- Los archivos privados se sirven con **URLs firmadas** (`/api/v1/media/videos/{id}`,
  `/api/v1/media/adjuntos/{id}`) con caducidad de 30 minutos.

## Endpoints

Todas las rutas llevan el prefijo `/api/v1`. `A` = requiere `Authorization: Bearer <token>`.

### Autenticación (`throttle:auth`)

| Método | Ruta | Descripción |
| --- | --- | --- |
| POST | `/auth/register/{rol}` | Registro (`inmobiliaria`, `vendedor`, `cliente`) + tokens |
| POST | `/auth/login` | Login por correo y contraseña |
| POST | `/auth/refresh` | Renueva el par de tokens |
| POST | `/auth/forgot-password` | Envía el enlace de recuperación |
| POST | `/auth/reset-password` | Restablece la contraseña con el token |
| GET | `/auth/me` | Perfil del usuario autenticado `A` |
| POST | `/auth/logout` · `/auth/logout-all` | Cierra la sesión actual / todas `A` |

### Catálogo público

| Método | Ruta | Descripción |
| --- | --- | --- |
| GET | `/propiedades` | Búsqueda con filtros `q, tipo, operacion, ciudad, estado_provincia, precio_min/max, moneda, habitaciones_min, banos_min, estacionamientos_min, area_min, destacada, con_video, amenidades[], amenidades_todas, cerca_de, radio_km, orden, page, per_page` |
| GET | `/propiedades/destacadas` | Destacadas de portada (`?limite=`) |
| GET | `/propiedades/{id}` | Detalle (incrementa vistas, registra métrica) |
| GET | `/amenidades` · `/ciudades` · `/catalogo/opciones` | Catálogos cacheados |
| GET | `/inmobiliarias` · `/inmobiliarias/{id}` | Directorio de anunciantes |
| POST | `/contacto` | Formulario de contacto (`throttle:contacto`) |

### Panel de inmobiliaria / vendedor (`A`, sustituye `{panel}` por `inmobiliaria` o `vendedor`)

| Método | Ruta | Descripción |
| --- | --- | --- |
| GET/POST | `/{panel}/propiedades` | Listado (con resumen por estado) / alta |
| GET/PATCH/DELETE | `/{panel}/propiedades/{id}` | Detalle / edición / baja |
| POST | `/{panel}/propiedades/{id}/publicar` · `/pausar` · `/cerrar-operacion` | Estados |
| GET | `/{panel}/propiedades/{id}/estadisticas` | Contadores y reportes |
| POST | `/{panel}/propiedades/{id}/fotos` | Sube fotos (`principal=1`) |
| PATCH | `/{panel}/propiedades/{id}/fotos/orden` · `/fotos/{foto}/principal` | Orden y portada |
| DELETE | `/{panel}/propiedades/{id}/fotos/{foto}` | Elimina una foto |
| POST/DELETE | `/{panel}/propiedades/{id}/video` | Video único (`reemplazar=1` para sustituir) |
| GET | `/{panel}/citas` · `/citas/agenda` · `/citas/{id}` | Agenda y detalle |
| PATCH | `/{panel}/citas/{id}/confirmar` · `/reprogramar` · `/cancelar` · `/completar` · `/no-asistio` | Transiciones |
| GET | `/{panel}/interesados` · `/interesados/{id}` | Bandeja de leads |
| POST | `/{panel}/interesados/{id}/responder` | Respuesta (con adjunto opcional) |
| PATCH | `/{panel}/interesados/{id}/estado` | Cambia el estado del embudo |
| GET/POST | `/{panel}/mensajes` · `/mensajes/{hilo}` | Bandeja de hilos y envío |
| PATCH | `/{panel}/mensajes/{hilo}/cerrar` · `/reabrir` | Estado del hilo |
| GET | `/{panel}/reportes/resumen` · `/propiedades-mas-vistas` · `/conversion` · `/ingresos` · `/exportar` | Reportes (`?desde=&hasta=&formato=pdf\|excel\|csv`) |
| GET/PATCH | `/{panel}/perfil` | Perfil del anunciante |
| POST/DELETE | `/{panel}/perfil/logo` | Logotipo (inmobiliaria) o foto (vendedor) |

### Panel de cliente (`A`, `role:cliente`)

| Método | Ruta | Descripción |
| --- | --- | --- |
| GET | `/cliente/propiedades` · `/cliente/propiedades/{id}` | Búsqueda personalizada y detalle |
| GET/POST | `/cliente/favoritos` | Listado / guardar |
| PATCH/DELETE | `/cliente/favoritos/{propiedad}` | Nota / quitar |
| POST | `/cliente/propiedades/{id}/interes` | Registra interés (correo al anunciante) |
| GET/DELETE | `/cliente/intereses` · `/intereses/{id}` | Seguimiento propio |
| GET/POST | `/cliente/mensajes` · `/mensajes/{hilo}` | Conversaciones |
| GET/POST | `/cliente/citas` · `/cliente/propiedades/{id}/citas` | Citas propias / solicitar |
| PATCH | `/cliente/citas/{id}/reprogramar` · `/cancelar` | Gestión de la cita |
| GET/PATCH | `/cliente/perfil` | Perfil, presupuesto y preferencias |

### Archivos firmados

| Método | Ruta | Descripción |
| --- | --- | --- |
| GET | `/media/videos/{video}` | Descarga el video (URL firmada, disk privado) |
| GET | `/media/adjuntos/{mensaje}` | Descarga el adjunto de un mensaje |

**Ejemplos completos de peticiones y respuestas**: [`docs/ejemplos-api.md`](docs/ejemplos-api.md).
**Colección de Postman**: [`docs/MercadoInmueble.postman_collection.json`](docs/MercadoInmueble.postman_collection.json).

## Archivos y medios

- **Fotos** → disk público (`storage/app/public`, servido por `storage:link`). Se redimensionan a
  1920 px de ancho y generan miniatura de 400 px (cuando `ext-gd` está disponible). Máximo 20 por
  propiedad y 10 por subida, 5 MB cada una (JPG/PNG/WEBP).
- **Videos** → disk privado (`storage/app/private` o **S3**). Máximo **uno por propiedad**, 100 MB,
  MP4/MOV/WEBM. Se entregan con URL firmada temporal.
- **Logotipos y fotos de perfil** → disk público, con validación de dimensiones (100–4000 px).
- **Adjuntos de mensajes** → disk privado, con URL firmada.

Configuración (`.env` → `config/mercado.php`): `MEDIA_DISCO_FOTOS`, `MEDIA_DISCO_VIDEOS`,
`MEDIA_MAX_FOTOS_POR_PROPIEDAD`, `MEDIA_MAX_FOTOS_POR_SUBIDA`, `MEDIA_MAX_PESO_FOTO_KB`,
`MEDIA_MAX_PESO_VIDEO_KB`, `MERCADO_URL_FIRMADA_MINUTOS`.

## Reportes y exportaciones

Las métricas se agregan **por día y propiedad** en la tabla `reportes`
(`vista`, `contacto`, `favorito`, `cita`, `conversion`) mediante *upsert* con bloqueo, de modo que
los reportes no recorren las tablas transaccionales.

- `resumen`: totales del periodo, publicaciones por estado y tendencia diaria.
- `propiedades-mas-vistas`: ranking por vistas del periodo.
- `conversion`: embudo vistas → contactos → citas → operaciones.
- `ingresos`: operaciones cerradas y comisión estimada (`MERCADO_COMISION_PORCENTAJE`, 3 % por defecto).
- `exportar?formato=pdf|excel|csv`: descarga con nombre `reporte-AAAAMMDD-HHMMSS.ext`.

## Rate limiting

Definido en `config/mercado.php` y aplicado con el limitador `api` (clave por usuario o IP):

| Ámbito | Límite por minuto |
| --- | --- |
| Invitado | 60 |
| Cliente | 120 |
| Vendedor | 240 |
| Inmobiliaria | 480 |
| `/auth/*` | 10 |
| `/contacto` | 5 |
| `/docs/api` | 60 |

Las respuestas `429` incluyen `code: "TOO_MANY_REQUESTS"` y `meta.retry_after`.

## Comandos artisan

| Comando | Descripción |
| --- | --- |
| `php artisan mercado:instalar [--fresh] [--seed] [--force]` | Instalación completa (claves, migraciones, Passport, storage) |
| `php artisan mercado:estado` | Diagnóstico: conexión, disks, cliente OAuth y volúmenes de datos |
| `php artisan openapi` | Exporta el contrato a `openapi.json` (`scramble:export`) |
| `php artisan db:seed` | Datos de demostración (nunca en producción) |
| `php artisan migrate:fresh --seed` | Reconstrucción completa del entorno de desarrollo |

## Pruebas

```bash
php artisan test              # suite completa (Unit + Feature)
php artisan test --filter=CitaEInteresTest
```

- Las pruebas usan **SQLite en memoria** (`DB_DATABASE=:memory:`) y `RefreshDatabase`, con
  `CACHE_STORE=array`, `MAIL_MAILER=array` y `QUEUE_CONNECTION=sync` (ver `phpunit.xml`).
- Cubren autenticación y roles, catálogo público con filtros, ciclo de vida de una publicación
  (fotos, video, publicación, cierre), aislamiento entre anunciantes, intereses y mensajería,
  agenda de citas con solapamientos, favoritos y reportes/exportaciones.
- `ext-gd` es recomendable para las pruebas que suben imágenes.

## Documentación OpenAPI

- UI interactiva: **`/docs/api`** (Scramble). En producción exige `Gate::viewApiDocs`: los correos
  de `DOCS_ALLOWED_EMAILS` o las IPs de `DOCS_ALLOWED_IPS` (en `local` es abierta).
- Contrato exportable: `php artisan openapi` → `openapi.json` (para SDKs, Postman o CI).

## Despliegue

1. **Servidor** (ejemplo con Nginx + PHP-FPM apuntando a `/public`):

```nginx
server {
    listen 443 ssl http2;
    server_name api.mercadoinmueble.com;
    root /var/www/mercado-inmueble-api/public;

    index index.php;
    client_max_body_size 120M;   # videos (100 MB)

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

2. **Aplicación**:

```bash
composer install --no-dev --optimize-autoloader
php artisan mercado:instalar            # sin --seed en producción
php artisan config:cache && php artisan route:cache && php artisan view:cache
APP_FORCE_HTTPS=true                    # redirige y genera URLs https
php artisan storage:link
```

3. **Colas** (correos y notificaciones `ShouldQueue`): `QUEUE_CONNECTION=redis|database` y un worker
   supervisado — procesa la cola `emails`:

```ini
[program:mercado-worker]
command=php /var/www/mercado-inmueble-api/artisan queue:work --queue=emails,default --tries=3 --sleep=1
numprocs=2
autostart=true
autorestart=true
user=www-data
```

4. **Scheduler** (retención del activity log, limpieza de tokens caducados):

```cron
* * * * * cd /var/www/mercado-inmueble-api && php artisan schedule:run >> /dev/null 2>&1
```

5. **Almacenamiento en S3** (recomendado para video): define `MEDIA_DISCO_VIDEOS=s3` y las
   credenciales `AWS_*`. El código genera *presigned URLs* automáticamente para el disk `s3`.
6. **Backups**: `mysqldump` diario de la base de datos + sincronización del disk público de fotos.

## Seguridad

- OAuth2 con Passport, tokens revocables, cuentas desactivables (`is_active` + middleware `activo`).
- Autorización en dos capas: middleware de rol y **policies por recurso** (propietario/participantes).
- Validación exhaustiva con Form Requests (mensajes en español, `declare(strict_types=1)` en todo el código).
- Consultas con *eager loading* y sin SQL interpolado; índices compuestos y *unique* para evitar
  duplicados (intereses, favoritos, videos, métricas).
- Cabeceras `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`,
  `Permissions-Policy`, HSTS y `Cache-Control` en respuestas autenticadas.
- Archivos privados fuera de `public`, entregados por URL firmada con caducidad.
- Rate limiting por tipo de usuario y por endpoint sensible; sin `statefulApi()` (API stateless).
- Auditoría de cambios de publicaciones y perfiles con `activity_log` (retención configurable).

## Estructura del proyecto

```
app/
├── Console/Commands/        Instalador (mercado:instalar) y diagnóstico (mercado:estado)
├── Contracts/               PropietarioDePropiedades (inmobiliaria|vendedor)
├── Enums/                   Role, TipoPropiedad, OperacionPropiedad, EstadoPropiedad,
│                            EstadoInteres, EstadoCita, TipoCita, TipoReporte,
│                            FormatoExportacion, Moneda, CategoriaAmenidad
├── Exceptions/              ApiExceptionHandler + BusinessException
├── Http/
│   ├── Controllers/Api/     Auth, Public, Panel (base), Inmobiliaria, Vendedor, Cliente
│   ├── Middleware/          ForceJsonResponse, SecurityHeaders, EnsureUserHasRole, EnsureAccountIsActive
│   ├── Requests/            Form Requests por caso de uso (Auth, Publico, Propiedad, Interes, Cita, Perfil, Reporte)
│   └── Resources/           14 JsonResources (propiedades, medios, intereses, hilos, citas, reportes…)
├── Mail/                    9 mailables (ShouldQueue) + BaseMailable + plantillas Blade HTML/texto
├── Models/                  16 modelos Eloquent
├── Notifications/           5 notificaciones de base de datos
├── Policies/                9 policies
├── Providers/               AppServiceProvider (Passport, limiters, caché) + AuthServiceProvider
├── Repositories/            Contrato + implementación de propiedades
├── Services/                11 servicios de dominio
└── Support/                 ApiResponse, Geo, PdfSimple, ExcelSimple
database/
├── factories/               15 factories
├── migrations/              24 migraciones (incluye Passport y activity_log)
└── seeders/                 Catálogos + datos de demostración
docs/                        Ejemplos JSON/cURL y colección de Postman
routes/                      api.php (v1), web.php (índice JSON), console.php
tests/                       Feature (auth, catálogo, panel, citas/intereses, reportes)
```

---

**Licencia**: MIT. © Mercado Inmueble.
