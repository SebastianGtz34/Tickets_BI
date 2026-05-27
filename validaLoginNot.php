<?php
/**
 * Tickets BI — endpoint de validación al hacer clic en una notificación.
 *
 * Lo invoca loginMaster (funcionesGlobales.js → construirUrlNotificacion) tras
 * marcar leída la notificación. Devuelve la URL destino y el front-end la abre
 * con ?id=<idRegistro> (caso especial ticketsBI, igual que entradasEq).
 *
 * Tickets BI NO mantiene sesión PHP propia. Solo valida que el empleado exista
 * y esté activo en mess_rrhh; el gating BI (gestionar_ticket) lo aplica la
 * página destino con requiereBiPage().
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once 'conn.php';

$noEmpleado = (int)($_POST['noEmpleado'] ?? 0);
$sistema    = $_POST['sistema']    ?? '';
$archivo    = $_POST['archivo']    ?? '';
$idRegistro = (int)($_POST['idRegistro'] ?? 0);

function responder(array $payload): void {
    echo json_encode($payload);
    exit;
}

if ($sistema !== 'ticketsBI') {
    responder([
        'success' => false,
        'status'  => 'error',
        'mensaje' => 'Sistema no corresponde a Tickets BI.'
    ]);
}

if ($noEmpleado <= 0) {
    responder([
        'success' => false,
        'status'  => 'error',
        'mensaje' => 'noEmpleado invalido.'
    ]);
}

// Validar usuario activo en mess_rrhh
$stmt = $conn->prepare(
    "SELECT 1 FROM mess_rrhh.usuarios WHERE noEmpleado = ? AND estatus = 1 LIMIT 1"
);
if (!$stmt) {
    responder([
        'success' => false,
        'status'  => 'error',
        'mensaje' => 'No se pudo preparar la validacion.'
    ]);
}
$stmt->bind_param('i', $noEmpleado);
$stmt->execute();
$valido = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$valido) {
    responder([
        'success' => false,
        'status'  => 'error',
        'mensaje' => 'Usuario no valido o inactivo.'
    ]);
}

// Mapear archivo -> URL destino. Las páginas leen ?id=<idTicket>.
$urlPorArchivo = [
    'gestionar_ticket' => '/Tickets/gestionar_ticket.php',
    'ver_ticket'       => '/Tickets/ver_ticket.php',
];

$urlDestino = $urlPorArchivo[$archivo] ?? '';
if ($urlDestino === '') {
    responder([
        'success' => false,
        'status'  => 'error',
        'mensaje' => 'Archivo no mapeado para Tickets BI.',
        'archivo' => $archivo
    ]);
}

responder([
    'success'    => true,
    'status'     => 'success',
    'mensaje'    => 'Validacion correcta.',
    'sistema'    => $sistema,
    'archivo'    => $archivo,
    'idRegistro' => $idRegistro,
    'urlDestino' => $urlDestino
]);
