# Ejemplos de la API — Mercado Inmueble

Todos los ejemplos usan la base `http://localhost:8000/api/v1`. Las respuestas
de la API comparten el mismo sobre (envelope):

```json
{ "success": true,  "message": "OK", "data": { }, "meta": { } }
{ "success": false, "message": "…", "code": "VALIDATION_ERROR", "errors": { } }
```

Sustituye `$TOKEN` por el `access_token` devuelto en el login o registro.

---

## 1. Autenticación

### Registro de inmobiliaria

```bash
curl -X POST http://localhost:8000/api/v1/auth/register/inmobiliaria \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{
    "name": "Inmobiliaria Demo",
    "email": "nueva@demo.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "phone": "+591 70000000",
    "razon_social": "Demo Propiedades S.R.L.",
    "ruc": "1023456789",
    "ciudad": "La Paz",
    "acepta_terminos": true
  }'
```

`201 Created`

```json
{
  "success": true,
  "message": "Registro exitoso. ¡Bienvenido!",
  "data": {
    "usuario": {
      "id": 1,
      "name": "Inmobiliaria Demo",
      "email": "nueva@demo.com",
      "role": { "value": "inmobiliaria", "label": "Inmobiliaria" },
      "perfil": { "id": 1, "razon_social": "Demo Propiedades S.R.L.", "ciudad": "La Paz" }
    },
    "tokens": {
      "token_type": "Bearer",
      "expires_in": 7200,
      "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOi…",
      "refresh_token": "def50200a1b2c3…"
    }
  }
}
```

Roles válidos: `inmobiliaria`, `vendedor`, `cliente` (cada uno con sus campos).

### Login

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email": "cliente1@demo.mercadoinmueble.com", "password": "Password123!"}'
```

`401 Unauthorized` con credenciales inválidas:

```json
{ "success": false, "message": "Correo o contraseña incorrectos.", "code": "CREDENCIALES_INVALIDAS", "errors": { "email": ["Correo o contraseña incorrectos."] } }
```

### Sesión (`/me`, refresh, logout)

```bash
curl http://localhost:8000/api/v1/auth/me -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"

curl -X POST http://localhost:8000/api/v1/auth/refresh \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"refresh_token": "def50200a1b2c3…"}'

curl -X POST http://localhost:8000/api/v1/auth/logout -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"
```

### Recuperación de contraseña

```bash
curl -X POST http://localhost:8000/api/v1/auth/forgot-password \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email": "cliente1@demo.mercadoinmueble.com"}'

curl -X POST http://localhost:8000/api/v1/auth/reset-password \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email": "cliente1@demo.mercadoinmueble.com", "token": "TOKEN_DEL_CORREO", "password": "NuevaPassword123!", "password_confirmation": "NuevaPassword123!"}'
```

---

## 2. Catálogo público

```bash
# Búsqueda con filtros, orden y paginación
curl "http://localhost:8000/api/v1/propiedades?ciudad=La%20Paz&tipo=departamento&operacion=venta&precio_min=40000&precio_max=120000&habitaciones_min=2&amenidades=1,3,5&amenidades_todas=1&orden=precio_asc&per_page=12" \
  -H "Accept: application/json"

# Geolocalización: latitud,longitud + radio
curl "http://localhost:8000/api/v1/propiedades?cerca_de=-16.50,-68.15&radio_km=5&orden=distancia" -H "Accept: application/json"

# Destacadas, catálogos y contacto
curl http://localhost:8000/api/v1/propiedades/destacadas -H "Accept: application/json"
curl http://localhost:8000/api/v1/amenidades -H "Accept: application/json"
curl http://localhost:8000/api/v1/ciudades -H "Accept: application/json"
curl http://localhost:8000/api/v1/catalogo/opciones -H "Accept: application/json"
curl http://localhost:8000/api/v1/inmobiliarias?verificadas=1 -H "Accept: application/json"

curl -X POST http://localhost:8000/api/v1/contacto \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"nombre":"Ana Torres","email":"ana@demo.com","telefono":"+591 71234567","asunto":"Quiero publicar","mensaje":"Tengo un departamento para alquilar en Sopocachi."}'
```

`200 OK` del listado:

```json
{
  "success": true,
  "message": "OK",
  "data": [
    {
      "id": 12,
      "codigo": "MI-2025-000012",
      "titulo": "Departamento luminoso en Sopocachi",
      "tipo": { "value": "departamento", "label": "Departamento" },
      "operacion": { "value": "venta", "label": "Venta" },
      "estado": { "value": "publicada", "label": "Publicada" },
      "destacada": false,
      "precio": 85000.0,
      "precio_formateado": "$us 85.000",
      "moneda": "USD",
      "habitaciones": 2,
      "banos": 2,
      "area_total": 95.5,
      "ciudad": "La Paz",
      "foto_principal": { "id": 33, "url": "http://localhost:8000/storage/propiedades/MI-2025-000012/foto-1.jpg", "es_principal": true },
      "distancia_km": 1.42,
      "publicada_en": "2025-09-02T14:31:00+00:00"
    }
  ],
  "links": { "first": "…?page=1", "last": "…?page=4", "prev": null, "next": "…?page=2" },
  "meta": { "current_page": 1, "per_page": 12, "total": 46 }
}
```

### Detalle (incrementa vistas)

```bash
curl http://localhost:8000/api/v1/propiedades/12 -H "Accept: application/json"
```

Devuelve `data.descripcion`, `data.fotos[]`, `data.video` (URL firmada si el disk es privado),
`data.amenidades[]`, `data.contacto` y `data.similares[]`.

---

## 3. Panel de inmobiliaria / vendedor

### Publicaciones

```bash
# Listado del panel con resumen por estado
curl http://localhost:8000/api/v1/inmobiliaria/propiedades?estado=publicada \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN"

# Crear con fotos y publicar en la misma petición (multipart)
curl -X POST http://localhost:8000/api/v1/inmobiliaria/propiedades \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -F "titulo=Departamento luminoso en Sopocachi" \
  -F "descripcion=Departamento de dos dormitorios con vista a la ciudad." \
  -F "tipo=departamento" -F "operacion=venta" -F "precio=85000" -F "moneda=USD" \
  -F "area_total=95.5" -F "habitaciones=2" -F "banos=2" \
  -F "direccion=Av. Arce #2450" -F "ciudad=La Paz" -F "estado_provincia=La Paz" \
  -F "latitud=-16.5041" -F "longitud=-68.1219" \
  -F "amenidades[]=1" -F "amenidades[]=4" \
  -F "fotos[]=@sala.jpg" -F "fotos[]=@cocina.jpg" -F "publicar=1"

# Actualizar, publicar, pausar y cerrar operación
curl -X PATCH http://localhost:8000/api/v1/inmobiliaria/propiedades/12 \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -d '{"precio": 92000, "destacada": true}'

curl -X POST http://localhost:8000/api/v1/inmobiliaria/propiedades/12/publicar  -H "Authorization: Bearer $TOKEN"
curl -X POST http://localhost:8000/api/v1/inmobiliaria/propiedades/12/pausar    -H "Authorization: Bearer $TOKEN"
curl -X POST http://localhost:8000/api/v1/inmobiliaria/propiedades/12/cerrar-operacion \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -d '{"estado": "vendida"}'

# Medios
curl -X POST http://localhost:8000/api/v1/inmobiliaria/propiedades/12/fotos \
  -H "Authorization: Bearer $TOKEN" -F "fotos[]=@frente.jpg" -F "principal=1"

curl -X PATCH http://localhost:8000/api/v1/inmobiliaria/propiedades/12/fotos/orden \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -d '{"fotos": [45, 44, 43]}'

curl -X PATCH http://localhost:8000/api/v1/inmobiliaria/propiedades/12/fotos/43/principal -H "Authorization: Bearer $TOKEN"
curl -X DELETE http://localhost:8000/api/v1/inmobiliaria/propiedades/12/fotos/44 -H "Authorization: Bearer $TOKEN"

# Video (máximo 1 por propiedad; 409 si ya existe y no envías reemplazar=1)
curl -X POST http://localhost:8000/api/v1/inmobiliaria/propiedades/12/video \
  -H "Authorization: Bearer $TOKEN" -F "video=@tour.mp4" -F "reemplazar=1"

curl -X GET "http://localhost:8000/api/v1/inmobiliaria/propiedades/12/estadisticas" -H "Authorization: Bearer $TOKEN"
```

Respuesta de error al publicar sin requisitos:

```json
{
  "success": false,
  "message": "La propiedad todavía no cumple los requisitos para publicarse.",
  "code": "REGLA_DE_NEGOCIO",
  "errors": { "propiedad": ["La propiedad todavía no cumple los requisitos para publicarse."] },
  "meta": { "requisitos_faltantes": ["fotos"] }
}
```

### Interesados y mensajes

```bash
curl "http://localhost:8000/api/v1/inmobiliaria/interesados?solo_nuevos=1" -H "Authorization: Bearer $TOKEN"
curl http://localhost:8000/api/v1/inmobiliaria/interesados/5 -H "Authorization: Bearer $TOKEN"

curl -X POST http://localhost:8000/api/v1/inmobiliaria/interesados/5/responder \
  -H "Authorization: Bearer $TOKEN" -F "cuerpo=Sigue disponible, ¿te parece el sábado a las 10?" -F "cerrar_interes=0"

curl -X PATCH http://localhost:8000/api/v1/inmobiliaria/interesados/5/estado \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -d '{"estado": "atendido", "nota": "Visitó la propiedad"}'

curl "http://localhost:8000/api/v1/inmobiliaria/mensajes?solo_no_leidos=1" -H "Authorization: Bearer $TOKEN"
curl http://localhost:8000/api/v1/inmobiliaria/mensajes/3 -H "Authorization: Bearer $TOKEN"
curl -X POST http://localhost:8000/api/v1/inmobiliaria/mensajes/3 -H "Authorization: Bearer $TOKEN" \
  -F "cuerpo=Te comparto el plano del departamento." -F "adjunto=@plano.pdf"
curl -X PATCH http://localhost:8000/api/v1/inmobiliaria/mensajes/3/cerrar -H "Authorization: Bearer $TOKEN"
```

### Citas y agenda

```bash
curl "http://localhost:8000/api/v1/inmobiliaria/citas?estado=pendiente&desde=2025-10-01&hasta=2025-10-31" -H "Authorization: Bearer $TOKEN"
curl "http://localhost:8000/api/v1/inmobiliaria/citas/agenda?fecha=2025-10-12" -H "Authorization: Bearer $TOKEN"

curl -X PATCH http://localhost:8000/api/v1/inmobiliaria/citas/9/confirmar   -H "Authorization: Bearer $TOKEN"
curl -X PATCH http://localhost:8000/api/v1/inmobiliaria/citas/9/reprogramar \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -d '{"fecha": "2025-10-15", "hora": "16:30", "motivo": "Cambio de agenda"}'
curl -X PATCH http://localhost:8000/api/v1/inmobiliaria/citas/9/cancelar \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -d '{"motivo": "El cliente no podrá asistir"}'
curl -X PATCH http://localhost:8000/api/v1/inmobiliaria/citas/9/completar -H "Authorization: Bearer $TOKEN"
curl -X PATCH http://localhost:8000/api/v1/inmobiliaria/citas/9/no-asistio -H "Authorization: Bearer $TOKEN"
```

### Reportes

```bash
curl "http://localhost:8000/api/v1/inmobiliaria/reportes/resumen?desde=2025-09-01&hasta=2025-09-30" -H "Authorization: Bearer $TOKEN"
curl "http://localhost:8000/api/v1/inmobiliaria/reportes/propiedades-mas-vistas?limite=10"          -H "Authorization: Bearer $TOKEN"
curl "http://localhost:8000/api/v1/inmobiliaria/reportes/conversion"                                 -H "Authorization: Bearer $TOKEN"
curl "http://localhost:8000/api/v1/inmobiliaria/reportes/ingresos"                                   -H "Authorization: Bearer $TOKEN"

# Exportaciones (pdf | excel | csv)
curl -OJ "http://localhost:8000/api/v1/inmobiliaria/reportes/exportar?reporte=resumen&formato=pdf"   -H "Authorization: Bearer $TOKEN"
curl -OJ "http://localhost:8000/api/v1/inmobiliaria/reportes/exportar?reporte=ingresos&formato=excel" -H "Authorization: Bearer $TOKEN"
```

Ejemplo de `resumen`:

```json
{
  "success": true,
  "message": "Reporte generado.",
  "data": {
    "periodo": { "desde": "2025-09-01", "hasta": "2025-09-30" },
    "totales": { "vistas": 1840, "contactos": 132, "favoritos": 74, "citas": 21, "conversiones": 3 },
    "por_estado": { "borrador": 1, "publicada": 18, "pausada": 2, "vendida": 1 },
    "tendencia": [ { "fecha": "2025-09-01", "vistas": 61, "contactos": 4 } ]
  }
}
```

### Perfil

```bash
curl http://localhost:8000/api/v1/inmobiliaria/perfil -H "Authorization: Bearer $TOKEN"
curl -X PATCH http://localhost:8000/api/v1/inmobiliaria/perfil \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -d '{"nombre_comercial": "Urbana Propiedades", "telefono": "+591 31234567"}'
curl -X POST http://localhost:8000/api/v1/inmobiliaria/perfil/logo -H "Authorization: Bearer $TOKEN" -F "logo=@logo.png"
curl -X DELETE http://localhost:8000/api/v1/inmobiliaria/perfil/logo -H "Authorization: Bearer $TOKEN"
```

El vendedor usa exactamente las mismas rutas con el prefijo `/api/v1/vendedor`.

---

## 4. Panel de cliente

```bash
# Búsqueda personalizada (aplica las preferencias guardadas del cliente)
curl "http://localhost:8000/api/v1/cliente/propiedades?per_page=10" -H "Authorization: Bearer $TOKEN"
curl http://localhost:8000/api/v1/cliente/propiedades/12 -H "Authorization: Bearer $TOKEN"

# Favoritos
curl -X POST http://localhost:8000/api/v1/cliente/favoritos \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -d '{"propiedad_id": 12, "nota": "Comparar con la casa de Calacoto"}'
curl http://localhost:8000/api/v1/cliente/favoritos -H "Authorization: Bearer $TOKEN"
curl -X PATCH http://localhost:8000/api/v1/cliente/favoritos/12 \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"nota": "Llamar el lunes"}'
curl -X DELETE http://localhost:8000/api/v1/cliente/favoritos/12 -H "Authorization: Bearer $TOKEN"

# Interés (envía correo al anunciante y abre el hilo de conversación)
curl -X POST http://localhost:8000/api/v1/cliente/propiedades/12/interes \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"mensaje": "Hola, me interesa la propiedad. ¿Sigue disponible?", "preferencia_contacto": "whatsapp"}'

curl http://localhost:8000/api/v1/cliente/intereses -H "Authorization: Bearer $TOKEN"
curl http://localhost:8000/api/v1/cliente/intereses/5 -H "Authorization: Bearer $TOKEN"

# Mensajes
curl http://localhost:8000/api/v1/cliente/mensajes -H "Authorization: Bearer $TOKEN"
curl -X POST http://localhost:8000/api/v1/cliente/mensajes/3 \
  -H "Authorization: Bearer $TOKEN" -F "cuerpo=¿Podemos visitarla el sábado?"

# Citas
curl -X POST http://localhost:8000/api/v1/cliente/propiedades/12/citas \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"fecha": "2025-10-20", "hora": "10:00", "tipo": "visita", "lugar": "Av. Arce #2450", "duracion_minutos": 45}'

curl http://localhost:8000/api/v1/cliente/citas -H "Authorization: Bearer $TOKEN"
curl -X PATCH http://localhost:8000/api/v1/cliente/citas/9/cancelar \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" -d '{"motivo": "Me surgió un viaje"}'

# Perfil y preferencias
curl -X PATCH http://localhost:8000/api/v1/cliente/perfil \
  -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"presupuesto_min": 60000, "presupuesto_max": 120000, "ciudad_interes": "La Paz", "habitaciones_min": 2, "recibe_novedades": true}'
```

---

## 5. Archivos privados (URLs firmadas)

Los videos y los adjuntos de mensajes viven en un disk privado. El recurso
devuelve una URL firmada temporal (30 minutos por defecto):

```json
{
  "video": {
    "url": "http://localhost:8000/api/v1/media/videos/7?expires=1760000000&signature=…",
    "privado": true,
    "duracion_legible": "01:35"
  }
}
```

```bash
curl -OJ "http://localhost:8000/api/v1/media/videos/7?expires=1760000000&signature=…"
```

---

## 6. Códigos de error

| Código | HTTP | Cuándo ocurre |
| --- | --- | --- |
| `VALIDATION_ERROR` | 422 | Datos inválidos (el detalle va en `errors`) |
| `UNAUTHENTICATED` | 401 | Falta el token o está expirado/revocado |
| `FORBIDDEN` | 403 | Rol sin permiso sobre el recurso (policies) |
| `NOT_FOUND` | 404 | Recurso inexistente o no visible |
| `METHOD_NOT_ALLOWED` | 405 | Verbo HTTP incorrecto |
| `TOO_MANY_REQUESTS` | 429 | Límite por rol o de endpoint (`meta.retry_after`) |
| `REGLA_DE_NEGOCIO` | 422/409 | Reglas del dominio (duplicados, estados, solapamientos) |
| `SERVER_ERROR` | 500 | Error inesperado (detalle sólo si `APP_DEBUG=true`) |
