{{ $titulo }}

{{ $saludo }}
{{ $intro }}

Fecha: {{ $cita->inicio()->translatedFormat('d \d\e F \d\e Y') }}
Horario: {{ $cita->rangoHorario() }}
Modalidad: {{ $cita->tipo->label() }}
Anunciante: {{ $propietario?->nombreComercial() }}
@if ($cita->lugar)
Lugar: {{ $cita->lugar }}
@endif
@if ($cita->enlace_virtual)
Enlace: {{ $cita->enlace_virtual }}
@endif
Propiedad: {{ $propiedad?->titulo }} ({{ $propiedad?->codigo }})

© {{ date('Y') }} {{ config('app.name') }}
