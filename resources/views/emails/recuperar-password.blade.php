@extends('emails.layouts.base')

@section('contenido')
    <p style="margin:0 0 14px;font-size:14px;">
        El enlace es válido durante <strong>{{ $minutos }} minutos</strong>. Si no solicitaste este cambio, ignora este correo:
        tu contraseña actual seguirá funcionando.
    </p>

    <p style="margin:0 0 6px;font-size:13px;color:#7b8794;word-break:break-all;">
        {{ $url }}
    </p>

    <p style="margin:18px 0 0;font-size:13px;color:#7b8794;">
        Código de verificación: <strong>{{ $token }}</strong>
    </p>
@endsection

@section('boton', $url)
@section('texto-boton', 'Restablecer contraseña')
