{{ $titulo }}

{{ $saludo }}
{{ $intro }}

Fecha: {{ $cita->inicio()->translatedFormat('d \d\e F \d\e Y') }} · {{ $cita->rangoHorario() }}
Propiedad: {{ $propiedad?->titulo }} ({{ $propiedad?->codigo }})
Cancelada por: {{ ucfirst($canceladaPor) }}
@if ($cita->motivo_cancelacion)
Motivo: {{ $cita->motivo_cancelacion }}
@endif

Puedes agendar una nueva cita cuando quieras.

© {{ date('Y') }} {{ config('app.name') }}
