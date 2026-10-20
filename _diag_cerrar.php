<?php
/**
 * _diag_cerrar.php — DIAGNÓSTICO TEMPORAL (BORRAR DESPUÉS DE USAR).
 *
 * Objetivo: descubrir por qué "Cerrar Ticket" no cambia el estado para un
 * empleado (reportado: 183), aunque sí aparece el modal de confirmación.
 * Recorre el mismo camino que el case 'cerrarTicket' de acciones_tickets.php
 * (permisos → UPDATE) y muestra lo que allá solo se ve como "no pasó nada".
 *
 * Uso (logueado como staff):
 *   _diag_cerrar.php?id=<id del ticket>&emp=183
 *     id  → ticket que no se deja cerrar (el número del ?id= de gestionar_ticket).
 *     emp → empleado a evaluar; por omisión, el de la sesión.
 *   Sin ?id muestra los últimos tickets abiertos para elegir uno.
 *
 * SEGURIDAD: no deja cambios. El UPDATE se ensaya dentro de una transacción con
 * ROLLBACK (tickets es InnoDB). No se generan notificaciones (notificacion_historial
 * es MyISAM y no se podría revertir). Aun así, BÓRRALO al terminar.
 */

header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

function linea(string $k, $v): void { echo str_pad($k, 30) . ': ' . $v . "\n"; }
function titulo(string $t): void { echo "\n== $t ==\n"; }
function si(bool $b): string { return $b ? 'SÍ' : 'NO'; }

echo "DIAGNÓSTICO CERRAR TICKET — Tickets Messbook\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/auth.php';

$noEmpSesion = ticketsAuthNoEmpleado();
if (!$noEmpSesion || !tieneAccesoTickets($conn, $noEmpSesion)) {
    echo "\nAcceso denegado: entra a Messbook como staff y recarga esta página.\n";
    exit;
}
$emp      = (int)($_GET['emp'] ?? 0) ?: $noEmpSesion;
$idTicket = (int)($_GET['id'] ?? 0);
linea('Sesión', $noEmpSesion);
linea('Empleado evaluado', $emp . ($emp === $noEmpSesion ? ' (el de la sesión)' : ' (por ?emp=)'));

// ─────────────────────────────────────────────────────────────────────────────
titulo('1) Entorno');

linea('PHP', PHP_VERSION);
linea('mysqli report_mode', (new mysqli_driver())->report_mode
    . '  (≠0 → un error SQL es excepción y la petición muere sin JSON)');
$sqlMode = $conn->query('SELECT @@SESSION.sql_mode AS m')->fetch_assoc()['m'] ?? '?';
linea('sql_mode', $sqlMode ?: '(vacío: no estricto)');

// ─────────────────────────────────────────────────────────────────────────────
titulo('2) Esquema de la tabla tickets');

$cols = [];
$rs = $conn->query("SHOW COLUMNS FROM tickets");
while ($c = $rs->fetch_assoc()) $cols[$c['Field']] = $c['Type'];
linea('estado', $cols['estado'] ?? '*** NO EXISTE ***');
linea('fecha_cierre', $cols['fecha_cierre'] ?? '*** NO EXISTE ***');
linea('fecha_actualizacion', $cols['fecha_actualizacion'] ?? '*** NO EXISTE ***');

if (isset($cols['estado']) && stripos($cols['estado'], 'enum') === 0 && strpos($cols['estado'], "'cerrado'") === false) {
    echo "\n>>> AQUÍ ESTÁ EL PROBLEMA: el ENUM de `estado` no incluye 'cerrado'.\n";
    echo ">>> El UPDATE falla (modo estricto) o guarda '' (no estricto).\n";
}
if (!isset($cols['fecha_cierre'])) {
    echo "\n>>> AQUÍ ESTÁ EL PROBLEMA: falta la columna fecha_cierre; el UPDATE de\n";
    echo ">>> cerrarTicket la usa y falla con 'Unknown column'.\n";
}

$trg = $conn->query("SHOW TRIGGERS WHERE `Table` = 'tickets'");
$nTrg = 0;
while ($t = $trg->fetch_assoc()) {
    $nTrg++;
    linea('Trigger', "{$t['Trigger']} ({$t['Timing']} {$t['Event']})");
}
if (!$nTrg) linea('Triggers en tickets', 'ninguno');

// ─────────────────────────────────────────────────────────────────────────────
titulo("3) Empleado $emp en mess_rrhh.usuarios");

$st = $conn->prepare("SELECT noEmpleado, nombre, departamento, estatus FROM mess_rrhh.usuarios WHERE noEmpleado = ?");
$st->bind_param('i', $emp);
$st->execute();
$filas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();
foreach ($filas as $u) {
    echo sprintf("   #%-6s %-34s depto=%-4s estatus=%s\n", $u['noEmpleado'], mb_substr($u['nombre'], 0, 34), $u['departamento'], $u['estatus']);
}
if (!$filas) echo "   (no existe)\n";
if (count($filas) > 1) {
    echo "\n>>> OJO: el empleado tiene " . count($filas) . " filas. obtenerNombreDepto() toma\n";
    echo ">>> la primera activa (LIMIT 1) y puede no ser la del departamento correcto.\n";
}
linea('Es staff (tieneAccesoTickets)', si(tieneAccesoTickets($conn, $emp)));
linea('Departamento (obtenerNombreDepto)', obtenerNombreDepto($conn, $emp) ?: '(ninguno)');

// ─────────────────────────────────────────────────────────────────────────────
if (!$idTicket) {
    titulo('4) Elige un ticket: últimos 15 no cerrados');
    $rs = $conn->query(
        "SELECT t.id, t.folio, t.estado, COALESCE(c.tipo,'(sin cat)') tipo
         FROM tickets t LEFT JOIN tickets_categorias c ON c.id = t.id_categoria
         WHERE t.estado NOT IN ('cerrado','cancelado') ORDER BY t.id DESC LIMIT 15"
    );
    while ($t = $rs->fetch_assoc()) {
        echo sprintf("   id=%-5d %-14s %-11s %s\n", $t['id'], $t['folio'], $t['estado'], $t['tipo']);
    }
    echo "\nVuelve a abrir con ?id=<id>&emp=$emp\n";
    exit;
}

titulo("4) Ticket id $idTicket");

$st = $conn->prepare(
    "SELECT t.id, t.folio, t.estado, t.fecha_cierre, c.nombre categoria, c.tipo
     FROM tickets t LEFT JOIN tickets_categorias c ON c.id = t.id_categoria WHERE t.id = ?"
);
$st->bind_param('i', $idTicket);
$st->execute();
$tk = $st->get_result()->fetch_assoc();
$st->close();
if (!$tk) { echo "No existe el ticket $idTicket.\n"; exit; }

linea('Folio', $tk['folio']);
linea('Estado actual', var_export($tk['estado'], true));
linea('Categoría / tipo', ($tk['categoria'] ?? '(sin categoría)') . ' / ' . ($tk['tipo'] ?? '—'));
$deptoTk = ticketDepartamento($conn, $idTicket);
linea('Departamento dueño', DEPT_CLAVES[$deptoTk] . " ($deptoTk)");
$asig = $conn->query("SELECT GROUP_CONCAT(no_empleado) a FROM tickets_asignados WHERE id_ticket = $idTicket")->fetch_assoc()['a'];
linea('Asignados', $asig ?: '(ninguno)');

$puede = puedeGestionarTicket($conn, $emp, $idTicket);
linea('puedeGestionarTicket', si($puede));
if (!$puede) {
    echo "\n>>> AQUÍ ESTÁ EL PROBLEMA: el servidor responde 'Este ticket pertenece a otro\n";
    echo ">>> departamento.' (toast rojo) y no actualiza. Departamento del empleado y\n";
    echo ">>> dueño del ticket no coinciden, y no está asignado.\n";
}
if ($tk['estado'] === '') {
    echo "\n>>> OJO: el estado está VACÍO. Un intento anterior guardó un valor que el\n";
    echo ">>> ENUM no acepta (ver sección 2).\n";
}

// ─────────────────────────────────────────────────────────────────────────────
titulo('5) Ensayo del UPDATE de cerrarTicket (transacción + ROLLBACK)');

$conn->begin_transaction();
try {
    $stmt = $conn->prepare(
        "UPDATE tickets SET estado = 'cerrado', fecha_cierre = NOW(), fecha_actualizacion = NOW() WHERE id = ?"
    );
    if (!$stmt) throw new Exception('prepare falló: ' . $conn->error);
    $stmt->bind_param('i', $idTicket);
    $ok = $stmt->execute();
    linea('execute()', $ok ? 'true' : 'false — ' . $stmt->error);
    linea('Filas afectadas', $stmt->affected_rows);
    $warn = $conn->query('SHOW WARNINGS');
    while ($w = $warn->fetch_assoc()) linea('Warning SQL', "{$w['Code']} {$w['Message']}");
    $stmt->close();
    $despues = $conn->query("SELECT estado FROM tickets WHERE id = $idTicket")->fetch_assoc()['estado'];
    linea('Estado dentro de la transacción', var_export($despues, true));
    if ($despues !== 'cerrado') {
        echo "\n>>> AQUÍ ESTÁ EL PROBLEMA: tras el UPDATE el estado no es 'cerrado'.\n";
    } elseif ($puede) {
        echo "\nEl UPDATE funciona para este ticket. Si en el navegador no cambia, el fallo\n";
        echo "es antes de llegar aquí (sesión/cookie distinta a $emp, caché del JS) o\n";
        echo "después (respuesta sin JSON): revisa la sección 6.\n";
    }
} catch (Throwable $e) {
    echo "\n>>> AQUÍ ESTÁ EL PROBLEMA: el UPDATE lanza una excepción:\n>>> " . $e->getMessage() . "\n";
    echo ">>> En acciones_tickets.php esto mata la petición sin JSON y nada cambia.\n";
} finally {
    $conn->rollback();
}
$final = $conn->query("SELECT estado FROM tickets WHERE id = $idTicket")->fetch_assoc()['estado'];
linea('Estado tras ROLLBACK', var_export($final, true) . ($final === $tk['estado'] ? '  (sin cambios, bien)' : '  *** CAMBIÓ ***'));

// ─────────────────────────────────────────────────────────────────────────────
titulo('6) error_log: errores recientes de acciones_tickets / notificaciones');

$logs = array_unique(array_map(fn($r) => realpath($r) ?: $r, array_filter([
    ini_get('error_log') ?: null,
    __DIR__ . '/error_log',
])));
foreach ($logs as $log) {
    if (!is_file($log) || !is_readable($log)) { linea(basename($log), 'no existe / ilegible'); continue; }
    $fh = fopen($log, 'r');
    fseek($fh, max(0, filesize($log) - 512 * 1024));
    // Solo líneas de Tickets: en local el log de WAMP es compartido por todos los proyectos.
    $lineas = preg_grep('/Tickets[\\\\\/](acciones_tickets|acciones_notificaciones|auth)\.php/', explode("\n", stream_get_contents($fh)));
    fclose($fh);
    linea($log, count($lineas) . ' línea(s) relevantes en los últimos 512 KB');
    foreach (array_slice($lineas, -20) as $l) echo '   ' . mb_substr($l, 0, 240) . "\n";
}

echo "\n\nFin del diagnóstico. BORRA ESTE ARCHIVO del servidor al terminar.\n";
