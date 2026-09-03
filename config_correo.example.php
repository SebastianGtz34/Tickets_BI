<?php
// ============================================================================
// PLANTILLA de configuración SMTP — copiar como config_correo.php (gitignored)
// y poner las credenciales reales. NUNCA commitear el app password.
// Patrón del ecosistema: cuenta Gmail con app password, SSL puerto 465
// (mismo esquema que ControlVehicular/includes/enviar_notificacion.php y SIVAC).
// ============================================================================
return [
    // Interruptor de envío. Con 'activo' => false NO se manda ningún correo
    // (útil en local/staging); la notificación in-app se sigue generando igual.
    // En producción ponerlo en true. Si se omite la clave, el envío está activo.
    'activo'      => true,
    'host'        => 'smtp.gmail.com',
    'port'        => 465,
    'secure'      => 'ssl',
    // Segundos de espera del SMTP. El correo se manda DENTRO de la petición que
    // crea el ticket, así que conviene un tope bajo.
    'timeout'     => 10,
    'usuario'     => 'cuenta@gmail.com',
    'password'    => 'app-password-de-16-letras',
    'from_correo' => 'cuenta@gmail.com',
    'from_nombre' => 'Tickets BI/TI — Grupo MESS',
];
