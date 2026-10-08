{{ $titulo }}

{{ $saludo }}
{{ $intro }}

Conversación: {{ $hilo?->asunto }}
Propiedad: {{ $propiedad?->titulo }} ({{ $propiedad?->codigo }})

Respuesta del anunciante:
"{{ $mensaje?->cuerpo }}"

Continuar la conversación: {{ $url }}

© {{ date('Y') }} {{ config('app.name') }}
