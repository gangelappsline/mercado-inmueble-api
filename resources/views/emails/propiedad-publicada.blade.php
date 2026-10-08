@extends('emails.layouts.base')

@section('contenido')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e7eb;border-radius:8px;font-size:14px;">
        <tr><td style="padding:10px 14px;color:#7b8794;">Código</td><td style="padding:10px 14px;font-weight:600;">{{ $propiedad->codigo }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Precio</td><td style="padding:10px 14px;">{{ $propiedad->precioFormateado() }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Ubicación</td><td style="padding:10px 14px;">{{ $propiedad->ciudad }} · {{ $propiedad->estado_provincia }}</td></tr>
        <tr><td style="padding:10px 14px;color:#7b8794;">Fotos</td><td style="padding:10px 14px;">{{ $propiedad->fotos->count() }}</td></tr>
    </table>
    <p style="margin:18px 0 0;font-size:13px;color:#7b8794;">
        Consejo: las publicaciones con 5 o más fotos reciben hasta 3 veces más contactos.
    </p>
@endsection

@section('boton', $url)
@section('texto-boton', 'Ver la publicación')
