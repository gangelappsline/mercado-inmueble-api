{{ $titulo }}

{{ $saludo }}
{{ $intro }}

Código: {{ $propiedad->codigo }}
Título: {{ $propiedad->titulo }}
Precio: {{ $propiedad->precioFormateado() }}
Ubicación: {{ $propiedad->ciudad }} · {{ $propiedad->estado_provincia }}
Fotos: {{ $propiedad->fotos->count() }}

Ver la publicación: {{ $url }}

© {{ date('Y') }} {{ config('app.name') }}
