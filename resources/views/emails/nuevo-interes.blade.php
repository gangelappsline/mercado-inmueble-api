@extends('emails.layouts.base')

@section('contenido')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e7eb;border-radius:8px;font-size:14px;">
        <tr><td style="padding:10px 14px;color:#7b8794;">Propiedad</td><td style="padding:10px 14px;font-weight:600;">{{ $propiedad?->titulo }} ({{ $propiedad?->codigo }})</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Ubicación</td><td style="padding:10px 14px;">{{ $propiedad?->ciudad }} · {{ $propiedad?->estado_provincia }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Cliente</td><td style="padding:10px 14px;">{{ $cliente?->nombreCompleto() }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Origen</td><td style="padding:10px 14px;">{{ strtoupper((string) $origen) }}</td></tr>
    </table>

    @if ($mensaje)
        <p style="margin:20px 0 8px;font-weight:600;">Mensaje del cliente</p>
        <blockquote style="margin:0;padding:12px 16px;border-left:3px solid #0f4c81;background:#f7f9fc;font-size:14px;line-height:1.6;">
            {{ $mensaje }}
        </blockquote>
    @endif
@endsection

@section('boton', $url)
@section('texto-boton', 'Responder ahora')
