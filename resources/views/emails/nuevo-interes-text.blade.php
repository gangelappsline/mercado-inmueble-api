{{ $titulo }}

{{ $saludo }}
{{ $intro }}

Propiedad: {{ $propiedad?->titulo }} ({{ $propiedad?->codigo }})
Ubicación: {{ $propiedad?->ciudad }} · {{ $propiedad?->estado_provincia }}
Cliente: {{ $cliente?->nombreCompleto() }}
Origen: {{ strtoupper((string) $origen) }}
@if ($mensaje)

Mensaje del cliente:
"{{ $mensaje }}"
@endif

Responder: {{ $url }}

Este correo se generó automáticamente, por favor no responda a esta dirección.
© {{ date('Y') }} {{ config('app.name') }}
