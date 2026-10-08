<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? config('app.name') }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2933;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(16,24,40,.08);">
    <tr>
        <td style="background:#0f4c81;padding:20px 28px;color:#ffffff;">
            <span style="font-size:18px;font-weight:700;letter-spacing:.4px;">{{ config('app.name') }}</span>
        </td>
    </tr>
    <tr>
        <td style="padding:28px;">
            <h1 style="margin:0 0 16px;font-size:20px;color:#0f4c81;">{{ $titulo }}</h1>

            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;">{{ $saludo }}</p>
            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#3e4c59;">{{ $intro }}</p>

            @yield('contenido')

            @hasSection('boton')
                <p style="margin:26px 0;">
                    <a href="@yield('boton')" style="display:inline-block;background:#0f4c81;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-size:14px;font-weight:600;">@yield('texto-boton', 'Ver en la plataforma')</a>
                </p>
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:18px 28px;background:#f7f9fc;color:#7b8794;font-size:12px;line-height:1.6;">
            Este correo se generó automáticamente, por favor no responda a esta dirección.<br>
            © {{ date('Y') }} {{ config('app.name') }} · {{ config('mercado.pais_por_defecto') }}
        </td>
    </tr>
</table>
</body>
</html>
