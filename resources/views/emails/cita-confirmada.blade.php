@extends('emails.layouts.base')

@section('contenido')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e7eb;border-radius:8px;font-size:14px;">
        <tr><td style="padding:10px 14px;color:#7b8794;">Fecha</td><td style="padding:10px 14px;font-weight:600;">{{ $cita->inicio()->translatedFormat('d \d\e F \d\e Y') }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Horario</td><td style="padding:10px 14px;">{{ $cita->rangoHorario() }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Modalidad</td><td style="padding:10px 14px;">{{ $cita->tipo->label() }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Anunciante</td><td style="padding:10px 14px;">{{ $propietario?->nombreComercial() }}</td></tr>
        @if ($cita->lugar)
            <tr><td style="padding:10px 14px;color:#7b8794;">Lugar</td><td style="padding:10px 14px;">{{ $cita->lugar }}</td></tr>
        @endif
        @if ($cita->enlace_virtual)
            <tr><td style="padding:10px 14px;color:#7b8794;">Enlace</td><td style="padding:10px 14px;">{{ $cita->enlace_virtual }}</td></tr>
        @endif
    </table>
    <p style="margin:18px 0 0;font-size:13px;color:#7b8794;">Propiedad: {{ $propiedad?->titulo }} ({{ $propiedad?->codigo }})</p>
@endsection
