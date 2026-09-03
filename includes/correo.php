<?php
/**
 * correo.php — Envío de correo por SMTP para Tickets BI/TI.
 *
 * Patrón del ecosistema (ControlVehicular / SIVAC): PHPMailer local, Gmail
 * SSL:465. Las credenciales viven en config_correo.php (gitignored), NUNCA en
 * código trackeado; config_correo.example.php es la plantilla.
 *
 * Regla dura: un fallo de SMTP JAMÁS debe abortar la operación de negocio ni
 * ensuciar la respuesta. Estas funciones no hacen echo ni mandan headers — se
 * llaman a mitad de peticiones que devuelven su propio JSON (acciones_tickets.php)
 * y cualquier salida aquí corrompería esa respuesta. Los errores se devuelven en
 * el arreglo de retorno y se registran con error_log().
 */

require_once __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer-master/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer-master/src/Exception.php';

if (!function_exists('tkConfigCorreo')) {

    /** URL pública del sistema, para los enlaces de los correos. */
    define('TK_URL_BASE', 'https://messbook.com.mx/Tickets');

    /** Logo institucional (URL absoluta: el correo se lee fuera del servidor). */
    define('TK_LOGO_URL', 'https://messbook.com.mx/incidencias/img/MESS_05_Imagotipo_1.png');

    /** Carga (y cachea) la configuración SMTP, o null si falta el archivo. */
    function tkConfigCorreo(): ?array {
        static $cfg = false;
        if ($cfg !== false) return $cfg;
        $ruta = __DIR__ . '/../config_correo.php';
        $cfg = is_file($ruta) ? (require $ruta) : null;
        return $cfg;
    }

    /**
     * Envuelve un contenido en la plantilla HTML institucional (azul MESS
     * Pantone 072 C, el mismo acento que usa css/estilos.css).
     * El $cuerpoHtml ya debe venir escapado por el llamador donde corresponda.
     */
    function tkPlantillaCorreo(string $titulo, string $cuerpoHtml): string {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;background:#f8f9fc;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">'
            . '<div style="max-width:640px;margin:24px auto;background:#ffffff;border-radius:8px;overflow:hidden;'
            . 'box-shadow:0 2px 8px rgba(0,0,0,.08);">'
            . '<div style="padding:20px 24px 12px;text-align:center;">'
            . '<img src="' . TK_LOGO_URL . '" alt="Grupo MESS" style="max-width:220px;height:auto;">'
            . '</div>'
            . '<div style="background:#050D9E;padding:12px 24px;text-align:center;color:#ffffff;'
            . 'font-size:17px;font-weight:bold;">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div style="padding:24px 32px;font-size:15px;line-height:1.6;">' . $cuerpoHtml . '</div>'
            . '<div style="padding:16px 32px;background:#f8f9fa;border-top:1px solid #e5e7eb;'
            . 'font-size:12px;color:#6c757d;text-align:center;line-height:1.7;">'
            . '<strong style="color:#4b5563;">Sistema de Tickets — Grupo MESS</strong><br>'
            . '<span style="color:#9ca3af;">Este buz&oacute;n es autom&aacute;tico: no respondas a esta direcci&oacute;n.</span>'
            . '</div></div></body></html>';
    }

    /**
     * Envía un correo HTML. Devuelve ['ok'=>bool, 'error'=>?string, 'para'=>string].
     * No lanza excepciones: cualquier fallo se reporta en el arreglo.
     *
     * @param string[] $para Lista de destinatarios (correos).
     * @param string[] $cc   Lista opcional de copias.
     */
    function tkEnviarCorreo(array $para, string $asunto, string $titulo, string $cuerpoHtml, array $cc = []): array {
        $cfg = tkConfigCorreo();

        $valido   = fn($c) => filter_var($c, FILTER_VALIDATE_EMAIL) !== false;
        $destinos = array_values(array_unique(array_filter(array_map('trim', $para), $valido)));
        $copias   = array_values(array_unique(array_filter(array_map('trim', $cc),   $valido)));
        $listaStr = implode(', ', array_merge($destinos, $copias));

        if (!$cfg) {
            return ['ok' => false, 'error' => 'Falta config_correo.php en el servidor.', 'para' => $listaStr];
        }
        // Interruptor de entorno: en local/staging se deja 'activo' => false para
        // no mandar correos reales. La notificación in-app se genera igual.
        if (array_key_exists('activo', $cfg) && !$cfg['activo']) {
            return ['ok' => false, 'error' => 'Envío de correo deshabilitado (config_correo.activo=false).', 'para' => $listaStr];
        }
        if (!$destinos) {
            return ['ok' => false, 'error' => 'Sin destinatarios válidos.', 'para' => $listaStr];
        }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->SMTPDebug  = 0;
            $mail->SMTPAuth   = true;
            $mail->SMTPSecure = $cfg['secure'] ?? 'ssl';
            $mail->Host       = $cfg['host'];
            $mail->Port       = (int)$cfg['port'];
            $mail->CharSet    = 'UTF-8';
            // Tope de espera: el envío ocurre DENTRO de la petición que crea el
            // ticket; sin esto un SMTP colgado dejaría al usuario esperando.
            $mail->Timeout    = (int)($cfg['timeout'] ?? 10);
            $mail->Username   = $cfg['usuario'];
            $mail->Password   = $cfg['password'];
            $mail->setFrom($cfg['from_correo'] ?? $cfg['usuario'], $cfg['from_nombre'] ?? 'Tickets MESS');
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = tkPlantillaCorreo($titulo, $cuerpoHtml);
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</tr>'], "\n", $cuerpoHtml)));

            // Los del equipo van en BCC-equivalente: addAddress a todos es lo que
            // ya hace el resto del ecosistema (son destinatarios internos).
            foreach ($destinos as $c) $mail->addAddress($c);
            foreach ($copias   as $c) $mail->addCC($c);

            $mail->send();
            return ['ok' => true, 'error' => null, 'para' => $listaStr];
        } catch (\Throwable $e) {
            error_log('Tickets correo: ' . $e->getMessage());
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 250), 'para' => $listaStr];
        }
    }
}
