{{ $titulo }}

{{ $saludo }}
{{ $intro }}

Rol: {{ $rol->label() }}
Usuario: {{ $usuario->email }}

{{ $url }}

© {{ date('Y') }} {{ config('app.name') }}
