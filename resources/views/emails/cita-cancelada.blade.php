@extends('emails.layouts.base')

@section('contenido')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e7eb;border-radius:8px;font-size:14px;">
        <tr><td style="padding:10px 14px;color:#7b8794;">Fecha</td><td style="padding:10px 14px;">{{ $cita->inicio()->translatedFormat('d \d\e F \d\e Y') }} · {{ $cita->rangoHorario() }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Propiedad</td><td style="padding:10px 14px;">{{ $propiedad?->titulo }} ({{ $propiedad?->codigo }})</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Cancelada por</td><td style="padding:10px 14px;">{{ ucfirst($canceladaPor) }}</td></tr>
        @if ($cita->motivo_cancelacion)
            <tr><td style="padding:10px 14px;color:#7b8794;">Motivo</td><td style="padding:10px 14px;">{{ $cita->motivo_cancelacion }}</td></tr>
        @endif
    </table>
    <p style="margin:18px 0 0;font-size:14px;">Puedes agendar una nueva cita cuando quieras desde la plataforma.</p>
@endsection
