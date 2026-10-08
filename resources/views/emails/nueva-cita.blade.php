@extends('emails.layouts.base')

@section('contenido')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e7eb;border-radius:8px;font-size:14px;">
        <tr><td style="padding:10px 14px;color:#7b8794;">Fecha</td><td style="padding:10px 14px;font-weight:600;">{{ $cita->inicio()->translatedFormat('d \d\e F \d\e Y') }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Horario</td><td style="padding:10px 14px;">{{ $cita->rangoHorario() }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Modalidad</td><td style="padding:10px 14px;">{{ $cita->tipo->label() }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Cliente</td><td style="padding:10px 14px;">{{ $cliente?->nombreCompleto() }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Propiedad</td><td style="padding:10px 14px;">{{ $propiedad?->titulo }} ({{ $propiedad?->codigo }})</td></tr>
        @if ($cita->enlace_virtual)
            <tr><td style="padding:10px 14px;color:#7b8794;">Enlace</td><td style="padding:10px 14px;">{{ $cita->enlace_virtual }}</td></tr>
        @endif
    </table>
@endsection

@section('boton', $url)
@section('texto-boton', 'Gestionar la cita')
