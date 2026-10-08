{{ $titulo }}

{{ $saludo }}
{{ $intro }}

Fecha: {{ $cita->inicio()->translatedFormat('d \d\e F \d\e Y') }}
Horario: {{ $cita->rangoHorario() }}
Modalidad: {{ $cita->tipo->label() }}
Cliente: {{ $cliente?->nombreCompleto() }}
Propiedad: {{ $propiedad?->titulo }} ({{ $propiedad?->codigo }})
@if ($cita->enlace_virtual)

Enlace: {{ $cita->enlace_virtual }}
@endif

Gestionar la cita: {{ $url }}

© {{ date('Y') }} {{ config('app.name') }}
