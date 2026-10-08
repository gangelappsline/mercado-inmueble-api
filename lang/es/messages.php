<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mensajes de la API (español)
|--------------------------------------------------------------------------
| Todo el texto que ve el cliente de la API sale de aquí: así la API queda
| lista para una segunda localización sin tocar el código de los servicios.
*/

return [

    // ─── Genéricos ────────────────────────────────────────────────────────
    'ok' => 'Operación exitosa.',
    'creado' => 'Recurso creado correctamente.',
    'actualizado' => 'Recurso actualizado correctamente.',
    'eliminado' => 'Recurso eliminado correctamente.',

    // ─── Errores ──────────────────────────────────────────────────────────
    'datos_invalidos' => 'Los datos enviados no son válidos.',
    'no_autenticado' => 'No autenticado: falta un token de acceso o expiró.',
    'no_autorizado' => 'No tiene permiso para realizar esta acción.',
    'rol_no_autorizado' => 'Su rol no tiene acceso a este recurso.',
    'cuenta_inactiva' => 'La cuenta está inactiva. Contacte con soporte.',
    'recurso_no_encontrado' => 'El recurso solicitado no existe.',
    'ruta_no_encontrada' => 'La ruta solicitada no existe.',
    'metodo_no_permitido' => 'Método HTTP no permitido para esta ruta.',
    'demasiadas_peticiones' => 'Demasiadas peticiones. Intente de nuevo más tarde.',
    'sesion_expirada' => 'La sesión expiró. Inicie sesión nuevamente.',
    'error_interno' => 'Ocurrió un error interno. Intente nuevamente en unos minutos.',
    'error_http' => 'La petición no pudo procesarse.',

    // ─── Autenticación ────────────────────────────────────────────────────
    'credenciales_invalidas' => 'Las credenciales proporcionadas son incorrectas.',
    'correo_no_registrado' => 'No existe una cuenta con ese correo electrónico.',
    'cuenta_no_verificada' => 'Debe verificar su correo electrónico antes de continuar.',
    'sesion_cerrada' => 'Sesión cerrada correctamente.',
    'token_invalido' => 'El token proporcionado no es válido o expiró.',
    'token_refrescado' => 'Token renovado correctamente.',
    'registro_exitoso' => 'Registro completado. Ya puede iniciar sesión.',
    'correo_enviado' => 'Si el correo está registrado, recibirá las instrucciones para restablecer su contraseña.',
    'password_restablecido' => 'Contraseña restablecida correctamente.',
    'rol_no_registrable' => 'Ese rol no admite registro público. Solicite la cuenta a un administrador.',

    // ─── Propiedades ──────────────────────────────────────────────────────
    'propiedad_no_encontrada' => 'La propiedad solicitada no existe o no está visible.',
    'propiedad_publicada' => 'La propiedad se publicó correctamente.',
    'propiedad_pausada' => 'La publicación se pausó correctamente.',
    'propiedad_no_publicable' => 'La propiedad no está lista para publicarse. Complete la información mínima (fotos, ubicación y precio).',
    'propiedad_limite_fotos' => 'Alcanzó el límite de fotos permitidas para esta propiedad.',
    'propiedad_video_duplicado' => 'La propiedad ya tiene un video. Elimínelo antes de subir otro.',
    'propiedad_sin_video' => 'La propiedad no tiene video.',
    'foto_no_encontrada' => 'La foto indicada no pertenece a esta propiedad.',
    'foto_principal_actualizada' => 'Foto principal actualizada.',

    // ─── Intereses y mensajes ─────────────────────────────────────────────
    'interes_registrado' => 'Su interés fue enviado. El anunciante se pondrá en contacto.',
    'interes_duplicado' => 'Ya registró interés en esta propiedad.',
    'interes_no_encontrado' => 'El interés indicado no existe.',
    'interes_propia_propiedad' => 'No puede registrar interés en su propia publicación.',
    'mensaje_enviado' => 'Mensaje enviado correctamente.',
    'hilo_cerrado' => 'El hilo de conversación está cerrado.',
    'hilo_no_encontrado' => 'El hilo de conversación no existe.',

    // ─── Citas ────────────────────────────────────────────────────────────
    'cita_solicitada' => 'Su solicitud de cita fue enviada. Espere la confirmación.',
    'cita_confirmada' => 'La cita fue confirmada.',
    'cita_reprogramada' => 'La cita fue reprogramada.',
    'cita_cancelada' => 'La cita fue cancelada.',
    'cita_completada' => 'La cita fue marcada como completada.',
    'cita_no_encontrada' => 'La cita indicada no existe.',
    'cita_solapada' => 'Ya existe una cita agendada en ese horario.',
    'cita_estado_invalido' => 'No es posible cambiar la cita al estado solicitado.',
    'cita_fecha_invalida' => 'La fecha de la cita no puede ser en el pasado.',
    'cita_fuera_de_rango' => 'La fecha de la cita está fuera del rango permitido.',
    'cita_agenda_completa' => 'El horario seleccionado ya no está disponible.',

    // ─── Favoritos ────────────────────────────────────────────────────────
    'favorito_agregado' => 'Propiedad agregada a favoritos.',
    'favorito_eliminado' => 'Propiedad eliminada de favoritos.',
    'favorito_duplicado' => 'La propiedad ya está en sus favoritos.',
    'favorito_no_existe' => 'La propiedad no está en sus favoritos.',

    // ─── Perfiles ─────────────────────────────────────────────────────────
    'perfil_actualizado' => 'Perfil actualizado correctamente.',
    'perfil_no_encontrado' => 'No se encontró el perfil para su usuario.',
    'logo_actualizado' => 'Logotipo actualizado correctamente.',
    'inmobiliaria_no_encontrada' => 'La inmobiliaria solicitada no existe.',
    'vendedor_no_encontrado' => 'El vendedor solicitado no existe.',

    // ─── Medios ───────────────────────────────────────────────────────────
    'archivo_subido' => 'Archivo subido correctamente.',
    'archivo_no_valido' => 'El archivo no cumple con el formato o tamaño permitido.',
    'archivo_no_encontrado' => 'El archivo solicitado no existe.',

    // ─── Contacto y reportes ──────────────────────────────────────────────
    'contacto_recibido' => 'Gracias por escribirnos. Responderemos a la brevedad.',
    'reporte_generado' => 'Reporte generado correctamente.',
    'sin_datos' => 'No hay datos para los filtros seleccionados.',

    // ─── Panel de administración ──────────────────────────────────────────
    'admin_usuario_no_encontrado' => 'La cuenta solicitada no existe.',
    'admin_usuario_creado' => 'Cuenta creada correctamente.',
    'admin_usuario_actualizado' => 'Cuenta actualizada correctamente.',
    'admin_usuario_activado' => 'La cuenta fue activada.',
    'admin_usuario_desactivado' => 'La cuenta fue desactivada y sus sesiones revocadas.',
    'admin_usuario_eliminado' => 'La cuenta fue dada de baja.',
    'admin_usuario_restaurado' => 'La cuenta fue restaurada.',
    'admin_rol_actualizado' => 'El rol de la cuenta fue actualizado.',
    'admin_rol_sin_perfil' => 'La cuenta no tiene el perfil extendido del rol indicado. Cree la cuenta con el registro correspondiente.',
    'admin_rol_sin_cambio' => 'La cuenta ya tiene ese rol.',
    'admin_auto_modificacion' => 'No puede aplicar esta acción sobre su propia cuenta.',
    'admin_ultimo_administrador' => 'Debe quedar al menos un administrador activo en la plataforma.',
    'admin_perfil_no_encontrado' => 'El perfil solicitado no existe.',
    'admin_verificacion_otorgada' => 'La cuenta quedó verificada.',
    'admin_verificacion_retirada' => 'Se retiró la verificación de la cuenta.',
    'admin_propiedad_destacada' => 'La publicación quedó destacada en la portada.',
    'admin_destacado_retirado' => 'Se retiró el destacado de la publicación.',
    'admin_propiedad_moderada' => 'La publicación fue moderada correctamente.',
    'admin_propiedad_ya_rechazada' => 'La publicación ya se encuentra rechazada.',
    'admin_contacto_atendido' => 'El mensaje de contacto quedó marcado como atendido.',
    'admin_actividad_no_encontrada' => 'El registro de actividad solicitado no existe.',

];
