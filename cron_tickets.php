<?php
/**
 * Cron de Tickets BI — pensado para ejecutarse desde el "Cron Job" de cPanel.
 *
 * Corre los 3 chequeos periódicos:
 *   1. Notifica tickets estancados (>=3 días sin movimiento).
 *   2. Notifica tickets sin asignar (>=2 días desde creación).
 *   3. Auto-cierre de tickets resueltos con 3+ días (tkCronCerrarResueltos).
 *
 * Forma de invocarlo en cPanel (solo CLI):
 *       /usr/local/bin/php /home/USUARIO/public_html/Tickets/cron_tickets.php
 *
 * Frecuencia sugerida en cPanel: una vez al día (p. ej. 0 7 * * *).
 *
 * Este script está restringido a ejecución por CLI: cualquier intento de
 * abrirlo por HTTP (navegador, wget/curl) se rechaza con 403.
 */

// Solo CLI. Bloquea cualquier invocación por HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden: este cron solo puede ejecutarse por CLI.';
    exit;
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php'; // ticketDepartamento() + constantes DEPT_* que usan las notifs
require_once __DIR__ . '/acciones_notificaciones.php';

$estancados = tkNotifCronEstancados($conn);
$sinAsignar = tkNotifCronSinAsignar($conn);
$cerrados   = tkCronCerrarResueltos($conn);

echo '[' . date('Y-m-d H:i:s') . '] Tickets BI cron — '
   . "estancados={$estancados}, sin_asignar={$sinAsignar}, cerrados={$cerrados}" . PHP_EOL;
