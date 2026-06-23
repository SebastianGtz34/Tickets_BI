<?php
/**
 * _diag_bi.php — DIAGNÓSTICO TEMPORAL (BORRAR DESPUÉS DE USAR).
 *
 * Objetivo: descubrir POR QUÉ tieneAccesoBi() devuelve false en producción
 * aunque el usuario sea de BI (depto 32) y la cookie esté presente.
 *
 * Replica EXACTAMENTE la consulta cross-DB de auth.php, pero mostrando los
 * errores que el código real traga en silencio (prepare/cross-DB/db name).
 *
 * Uso: abrir https://TU-DOMINIO/Tickets/_diag_bi.php estando logueado.
 *      (opcional) ?no=523 para forzar un noEmpleado distinto al de la cookie.
 *
 * SEGURIDAD: solo lee. Aun así, BÓRRALO al terminar el diagnóstico.
 */

header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

function linea(string $k, $v): void { echo str_pad($k, 28) . ': ' . $v . "\n"; }
function titulo(string $t): void { echo "\n== $t ==\n"; }

echo "DIAGNÓSTICO ACCESO BI/TI — Tickets\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n";

titulo('1) Cookies recibidas');
$cookieBI = $_COOKIE['noEmpleadoBI'] ?? '(ausente)';
$cookieL  = $_COOKIE['noEmpleadoL'] ?? '(ausente)';
linea('noEmpleadoBI', $cookieBI);
linea('noEmpleadoL',  $cookieL);

$noEmpleado = (int)($_GET['no'] ?? ($_COOKIE['noEmpleadoBI'] ?? $_COOKIE['noEmpleadoL'] ?? 0));
linea('noEmpleado a evaluar', $noEmpleado ?: '(0 — sin cookie ni ?no=)');

titulo('2) Conexión (conn.php de PRODUCCIÓN)');
require_once 'conn.php';
if (!isset($conn) || $conn->connect_error) {
    linea('connect_error', isset($conn) ? $conn->connect_error : 'no se creó $conn');
    echo "\n>>> La conexión base falló. Revisa credenciales en conn.php.\n";
    exit;
}
linea('host_info', $conn->host_info);
$dbActual = $conn->query("SELECT DATABASE() AS db")->fetch_assoc()['db'] ?? '(?)';
linea('Base por defecto', $dbActual);
linea('Usuario MySQL', $conn->query("SELECT CURRENT_USER() AS u")->fetch_assoc()['u'] ?? '(?)');

titulo('3) ¿El usuario MySQL puede LEER mess_rrhh? (cross-DB)');
$probe = $conn->query("SELECT COUNT(*) AS n FROM mess_rrhh.usuarios");
if ($probe === false) {
    linea('Resultado', 'FALLÓ');
    linea('errno', $conn->errno);
    linea('error', $conn->error);
    echo "\n>>> AQUÍ ESTÁ EL PROBLEMA: la conexión de Tickets NO puede leer\n";
    echo ">>> mess_rrhh.usuarios. Por eso auth.php devuelve false (te trata como no-BI).\n";
    echo ">>> Causas típicas: (a) falta GRANT SELECT ON mess_rrhh.* al usuario de\n";
    echo ">>> Tickets, o (b) en prod la base de RRHH NO se llama 'mess_rrhh'.\n";
} else {
    $n = $probe->fetch_assoc()['n'];
    linea('Resultado', 'OK — mess_rrhh.usuarios accesible');
    linea('Filas en usuarios', $n);
}

titulo('4) Consulta EXACTA de auth.php para tu noEmpleado');
// Igual que tieneAccesoTickets(): departamento IN (32,38) AND estatus = 1
$stmt = $conn->prepare(
    "SELECT departamento, estatus FROM mess_rrhh.usuarios WHERE noEmpleado = ? LIMIT 1"
);
if (!$stmt) {
    linea('prepare()', 'FALLÓ (esto es lo que auth.php traga en silencio → false)');
    linea('error', $conn->error);
} else {
    $stmt->bind_param('i', $noEmpleado);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        linea('Fila encontrada', 'NO (noEmpleado no existe en mess_rrhh.usuarios)');
    } else {
        linea('departamento', $row['departamento']);
        linea('estatus', $row['estatus']);
        $esBi = in_array((int)$row['departamento'], [32, 38], true) && (int)$row['estatus'] === 1;
        linea('¿Pasa tieneAccesoBi?', $esBi ? 'SÍ (debería detectarte como BI/TI)' : 'NO');
        if (!$esBi) {
            echo "\n>>> Con estos datos el código te clasifica como NO-BI:\n";
            if (!in_array((int)$row['departamento'], [32, 38], true)) {
                echo ">>> tu departamento ($row[departamento]) no es 32 ni 38.\n";
            }
            if ((int)$row['estatus'] !== 1) {
                echo ">>> tu estatus ($row[estatus]) no es 1.\n";
            }
        }
    }
}

titulo('5) Veredicto');
echo "Si el paso 3 FALLÓ -> es permiso/nombre de BD cross-DB (lo más probable).\n";
echo "Si el paso 3 fue OK pero el paso 4 dio depto != 32/38 -> es dato del usuario.\n";
echo "\n(Recuerda BORRAR este archivo: _diag_bi.php)\n";
