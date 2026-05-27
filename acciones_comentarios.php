<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'conn.php';
require_once 'auth.php';
require_once 'acciones_notificaciones.php';

// Sesión obligatoria. Identidad y rol BI derivan del servidor, no del cliente.
$noEmpSesion = requiereSesionJson();
$esBiSesion  = tieneAccesoBi($conn, $noEmpSesion);

$accion     = $_POST['accion'] ?? '';
$noEmpleado = (string)$noEmpSesion;

function responder(bool $success, string $message = '', array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

switch ($accion) {

    // ── AGREGAR COMENTARIO ─────────────────────────────────────────────────────
    case 'agregarComentario': {
        $idTicket  = (int)($_POST['id_ticket']  ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');
        // Solo BI puede marcar comentario como interno
        $esInterno  = ($esBiSesion && (int)($_POST['es_interno'] ?? 0) === 1) ? 1 : 0;

        if (!$idTicket || !$comentario) {
            responder(false, 'Faltan datos obligatorios.');
        }

        // Tickets cerrados no aceptan comentarios.
        $chk = $conn->prepare("SELECT estado FROM tickets WHERE id = ?");
        $chk->bind_param('i', $idTicket);
        $chk->execute();
        $estadoRow = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$estadoRow) responder(false, 'Ticket no encontrado.');
        if ($estadoRow['estado'] === 'cerrado') {
            responder(false, 'Este ticket está cerrado. No se pueden agregar comentarios.');
        }

        $stmt = $conn->prepare(
            "INSERT INTO tickets_comentarios (id_ticket, no_empleado, comentario, es_interno)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('issi', $idTicket, $noEmpleado, $comentario, $esInterno);
        if (!$stmt->execute()) {
            responder(false, 'Error al guardar el comentario.');
        }
        $idComentario = (int)$conn->insert_id;
        $stmt->close();

        // Actualizar fecha_actualizacion del ticket
        $upd = $conn->prepare("UPDATE tickets SET fecha_actualizacion = NOW() WHERE id = ?");
        $upd->bind_param('i', $idTicket);
        $upd->execute();
        $upd->close();

        // Subir adjunto opcional
        if (!empty($_FILES['adjunto']['name']) && $_FILES['adjunto']['error'] === UPLOAD_ERR_OK) {
            $maxBytes   = 10 * 1024 * 1024;
            $permitidos = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','zip','txt','csv'];
            $file = $_FILES['adjunto'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file['size'] <= $maxBytes && in_array($ext, $permitidos, true)) {
                $nombreUnico = uniqid('adj_', true) . '.' . $ext;
                $destino     = __DIR__ . '/uploads/' . $nombreUnico;
                if (move_uploaded_file($file['tmp_name'], $destino)) {
                    $nombre = $conn->real_escape_string(basename($file['name']));
                    $tipo   = $conn->real_escape_string($file['type']);
                    $tam    = (int)$file['size'];
                    $stmt2  = $conn->prepare(
                        "INSERT INTO tickets_adjuntos (id_ticket, id_comentario, nombre_archivo, ruta, tipo, tamano)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    $stmt2->bind_param('iisssi', $idTicket, $idComentario, $nombre, $nombreUnico, $tipo, $tam);
                    $stmt2->execute();
                    $stmt2->close();
                }
            }
        }

        // Notificar al destinatario apropiado (solicitante o BI)
        tkNotificarComentario($conn, $idTicket, $idComentario, $comentario, (int)$noEmpleado, $esInterno === 1, $esBiSesion);

        responder(true, '', ['id' => $idComentario]);
    }

    // ── OBTENER COMENTARIOS ────────────────────────────────────────────────────
    case 'obtenerComentarios': {
        $idTicket = (int)($_POST['id_ticket'] ?? 0);
        // es_bi siempre se deriva del servidor — no confiar en el cliente
        $esBi     = $esBiSesion ? 1 : 0;

        if (!$idTicket) responder(false, 'ID de ticket inválido.');

        // Si no es BI, excluir notas internas
        $filtroInterno = $esBi ? '' : 'AND tc.es_interno = 0';

        $sql = "SELECT tc.*, ta.nombre_archivo, ta.ruta, ta.tamano
                FROM tickets_comentarios tc
                LEFT JOIN tickets_adjuntos ta ON ta.id_comentario = tc.id
                WHERE tc.id_ticket = ? $filtroInterno
                ORDER BY tc.fecha ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $res = $stmt->get_result();

        $comentarios = [];
        while ($row = $res->fetch_assoc()) {
            $adjunto = null;
            if ($row['nombre_archivo']) {
                $adjunto = [
                    'nombre_archivo' => $row['nombre_archivo'],
                    'ruta'           => $row['ruta'],
                    'tamano'         => $row['tamano'],
                ];
            }
            // Nombre amigable (si no hay tabla de empleados, usamos el número)
            $row['nombre_empleado'] = 'Empleado #' . $row['no_empleado'];
            $row['adjunto']         = $adjunto;
            unset($row['nombre_archivo'], $row['ruta'], $row['tamano']);
            $comentarios[] = $row;
        }
        $stmt->close();

        responder(true, '', ['comentarios' => $comentarios]);
    }

    default:
        responder(false, 'Acción no reconocida.');
}
