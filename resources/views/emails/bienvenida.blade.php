@extends('emails.layouts.base')

@section('contenido')
    <p style="margin:0 0 14px;font-size:14px;">
        Tu cuenta tiene el rol <strong>{{ $rol->label() }}</strong> y ya puedes iniciar sesión con <strong>{{ $usuario->email }}</strong>.
    </p>

    <ul style="margin:0 0 6px;padding-left:20px;font-size:14px;line-height:1.8;color:#3e4c59;">
        @if ($rol->publicaPropiedades())
            <li>Publica propiedades con fotos y video (un video por propiedad).</li>
            <li>Gestiona tu agenda de visitas y las consultas de interesados.</li>
            <li>Consulta reportes de vistas, contactos y conversiones.</li>
        @else
            <li>Busca propiedades con filtros avanzados y geolocalización.</li>
            <li>Guarda favoritos y solicita visitas en línea.</li>
            <li>Conversa directamente con el anunciante.</li>
        @endif
    </ul>
@endsection

@section('boton', $url)
@section('texto-boton', 'Entrar a la plataforma')
