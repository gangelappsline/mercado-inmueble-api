{{ $titulo }}

{{ $saludo }}
{{ $intro }}
@if ($fechaAnterior)

Antes: {{ $fechaAnterior }} {{ $horaAnterior }}
@endif

Nueva fecha: {{ $cita->inicio()->translatedFormat('d \d\e F \d\e Y') }}
Nuevo horario: {{ $cita->rangoHorario() }}
Modalidad: {{ $cita->tipo->label() }}
Propiedad: {{ $propiedad?->titulo }} ({{ $propiedad?->codigo }})

© {{ date('Y') }} {{ config('app.name') }}
