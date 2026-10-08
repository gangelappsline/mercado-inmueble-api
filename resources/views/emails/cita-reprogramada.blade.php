@extends('emails.layouts.base')

@section('contenido')
    @if ($fechaAnterior)
        <p style="margin:0 0 14px;font-size:14px;color:#7b8794;">
            Antes: <s>{{ $fechaAnterior }} {{ $horaAnterior }}</s>
        </p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e7eb;border-radius:8px;font-size:14px;">
        <tr><td style="padding:10px 14px;color:#7b8794;">Nueva fecha</td><td style="padding:10px 14px;font-weight:600;">{{ $cita->inicio()->translatedFormat('d \d\e F \d\e Y') }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Nuevo horario</td><td style="padding:10px 14px;">{{ $cita->rangoHorario() }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Modalidad</td><td style="padding:10px 14px;">{{ $cita->tipo->label() }}</td></tr>
    </table>
    <p style="margin:18px 0 0;font-size:13px;color:#7b8794;">Propiedad: {{ $propiedad?->titulo }} ({{ $propiedad?->codigo }})</p>
@endsection
