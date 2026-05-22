<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'conn.php';
require_once 'auth.php';

// Sesión válida obligatoria. El cliente puede pasar no_empleado pero solo se usa
// para datos; las decisiones de privilegios derivan SIEMPRE del servidor.
$noEmpSesion = requiereSesionJson();
$esBiSesion  = tieneAccesoBi($conn, $noEmpSesion);

$accion     = $_POST['accion']     ?? $_GET['accion']     ?? '';
$noEmpleado = (string)$noEmpSesion;

// ── Helpers ──────────────────────────────────────────────────────────────────

function responder(bool $success, string $message = '', array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function generarFolio(mysqli $conn): string {
    $anio = date('Y');
    $sql  = "SELECT COUNT(*) AS total FROM tickets WHERE YEAR(fecha_creacion) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $anio);
    $stmt->execute();
    $n = (int)$stmt->get_result()->fetch_assoc()['total'] + 1;
    $stmt->close();
    return sprintf('TKT-%d-%03d', $anio, $n);
}

function subirAdjunto(array $file, mysqli $conn, int $idTicket, ?int $idComentario = null): void {
    $maxBytes   = 10 * 1024 * 1024; // 10 MB
    $permitidos = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','zip','txt','csv'];
    $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== UPLOAD_ERR_OK) return;
    if ($file['size'] > $maxBytes) return;
    if (!in_array($ext, $permitidos, true)) return;

    $nombreUnico = uniqid('adj_', true) . '.' . $ext;
    $destino     = __DIR__ . '/uploads/' . $nombreUnico;

    if (!move_uploaded_file($file['tmp_name'], $destino)) return;

    $nombre = $conn->real_escape_string(basename($file['name']));
    $tipo   = $conn->real_escape_string($file['type']);
    $tam    = (int)$file['size'];

    $stmt = $conn->prepare(
        "INSERT INTO tickets_adjuntos (id_ticket, id_comentario, nombre_archivo, ruta, tipo, tamano)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('iisssi', $idTicket, $idComentario, $nombre, $nombreUnico, $tipo, $tam);
    $stmt->execute();
    $stmt->close();
}

// ── Router ───────────────────────────────────────────────────────────────────

switch ($accion) {

    // ── CREAR TICKET ─────────────────────────────────────────────────────────
    case 'crearTicket': {
        $titulo      = trim($_POST['titulo']      ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $link        = trim($_POST['link']        ?? '');
        $idCat       = (int)($_POST['id_categoria'] ?? 0);
        $prioridad   = $_POST['prioridad'] ?? 'media';
        $prioridadesValidas = ['baja','media','alta','urgente'];

        if (!$titulo || !$descripcion || !$idCat) {
            responder(false, 'Faltan campos obligatorios.');
        }
        if (!in_array($prioridad, $prioridadesValidas, true)) {
            responder(false, 'Prioridad inválida.');
        }
        if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) {
            responder(false, 'El enlace de referencia no es una URL válida.');
        }
        $linkValue = $link !== '' ? $link : null;

        $folio = generarFolio($conn);

        $stmt = $conn->prepare(
            "INSERT INTO tickets (folio, titulo, descripcion, link, id_categoria, prioridad, estado, no_empleado_solicitante)
             VALUES (?, ?, ?, ?, ?, ?, 'nuevo', ?)"
        );
        $stmt->bind_param('ssssiss', $folio, $titulo, $descripcion, $linkValue, $idCat, $prioridad, $noEmpleado);
        if (!$stmt->execute()) {
            responder(false, 'Error al crear el ticket.');
        }
        $idTicket = (int)$conn->insert_id;
        $stmt->close();

        // Subir adjuntos (múltiples)
        if (!empty($_FILES['adjuntos']['name'][0])) {
            $adjuntos = $_FILES['adjuntos'];
            $total    = count($adjuntos['name']);
            for ($i = 0; $i < min($total, 5); $i++) {
                $f = [
                    'name'     => $adjuntos['name'][$i],
                    'type'     => $adjuntos['type'][$i],
                    'tmp_name' => $adjuntos['tmp_name'][$i],
                    'error'    => $adjuntos['error'][$i],
                    'size'     => $adjuntos['size'][$i],
                ];
                subirAdjunto($f, $conn, $idTicket);
            }
        }

        responder(true, 'Ticket creado.', ['folio' => $folio, 'id' => $idTicket]);
    }

    // ── OBTENER TICKETS ───────────────────────────────────────────────────────
    case 'obtenerTickets': {
        // es_bi se deriva del servidor — ignorar lo que mande el cliente.
        // BI ve todo; no-BI ve solo los suyos (filtro forzado server-side).
        $esBi       = $esBiSesion ? 1 : 0;
        $estado     = $_POST['estado']     ?? '';
        $prioridad  = $_POST['prioridad']  ?? '';
        $fechaDesde = $_POST['fecha_desde'] ?? '';
        $fechaHasta = $_POST['fecha_hasta'] ?? '';
        $soloAsig   = (int)($_POST['solo_asignado'] ?? 0);
        $limite     = (int)($_POST['limite'] ?? 0);

        $where = ['1=1'];
        $params = [];
        $types  = '';

        if (!$esBi) {
            $where[] = 't.no_empleado_solicitante = ?';
            $params[] = $noEmpleado;
            $types   .= 's';
        }
        if ($soloAsig && $esBi) {
            $where[] = 't.no_empleado_asignado = ?';
            $params[] = $noEmpleado;
            $types   .= 's';
        }
        if ($estado) {
            $where[] = 't.estado = ?';
            $params[] = $estado;
            $types   .= 's';
        }
        if ($prioridad) {
            $where[] = 't.prioridad = ?';
            $params[] = $prioridad;
            $types   .= 's';
        }
        if ($fechaDesde) {
            $where[] = 'DATE(t.fecha_creacion) >= ?';
            $params[] = $fechaDesde;
            $types   .= 's';
        }
        if ($fechaHasta) {
            $where[] = 'DATE(t.fecha_creacion) <= ?';
            $params[] = $fechaHasta;
            $types   .= 's';
        }

        $whereStr = implode(' AND ', $where);
        $limitStr = $limite > 0 ? "LIMIT $limite" : '';
        $sql = "SELECT t.*, c.nombre AS categoria
                FROM tickets t
                LEFT JOIN tickets_categorias c ON t.id_categoria = c.id
                WHERE $whereStr
                ORDER BY t.fecha_creacion DESC
                $limitStr";

        $stmt = $conn->prepare($sql);
        if ($types) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $tickets = [];
        while ($row = $res->fetch_assoc()) $tickets[] = $row;
        $stmt->close();

        // DataTables wrapper
        responder(true, '', [
            'tickets'          => $tickets,
            'recordsTotal'     => count($tickets),
            'recordsFiltered'  => count($tickets)
        ]);
    }

    // ── OBTENER TICKET (detalle) ───────────────────────────────────────────
    case 'obtenerTicket': {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if (!$id) responder(false, 'ID inválido.');

        $stmt = $conn->prepare(
            "SELECT t.*, c.nombre AS categoria
             FROM tickets t
             LEFT JOIN tickets_categorias c ON t.id_categoria = c.id
             WHERE t.id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ticket) responder(false, 'Ticket no encontrado.');

        // Si no es BI, solo puede ver el detalle de tickets que él creó
        if (!$esBiSesion && (string)$ticket['no_empleado_solicitante'] !== (string)$noEmpleado) {
            responder(false, 'No tienes permiso para ver este ticket.');
        }

        // Adjuntos del ticket (sin id_comentario)
        $stmt2 = $conn->prepare(
            "SELECT * FROM tickets_adjuntos WHERE id_ticket = ? AND id_comentario IS NULL ORDER BY fecha DESC"
        );
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $adjRes = $stmt2->get_result();
        $adjuntos = [];
        while ($a = $adjRes->fetch_assoc()) $adjuntos[] = $a;
        $stmt2->close();

        responder(true, '', ['ticket' => $ticket, 'adjuntos' => $adjuntos]);
    }

    // ── ACTUALIZAR ESTADO ─────────────────────────────────────────────────────
    case 'actualizarEstado': {
        requiereBiJson($conn, $noEmpSesion);
        $id     = (int)($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        $validos = ['nuevo','en_proceso','pendiente','resuelto','cerrado'];
        if (!$id || !in_array($estado, $validos, true)) responder(false, 'Datos inválidos.');

        $stmt = $conn->prepare(
            "UPDATE tickets SET estado = ?, fecha_actualizacion = NOW() WHERE id = ?"
        );
        $stmt->bind_param('si', $estado, $id);
        $ok = $stmt->execute();
        $stmt->close();
        responder($ok, $ok ? '' : 'Error al actualizar estado.');
    }

    // ── ASIGNAR TICKET ─────────────────────────────────────────────────────────
    case 'asignarTicket': {
        requiereBiJson($conn, $noEmpSesion);
        $id       = (int)($_POST['id'] ?? 0);
        $asignado = trim($_POST['no_empleado_asignado'] ?? '');
        if (!$id || !$asignado) responder(false, 'Datos inválidos.');

        $stmt = $conn->prepare(
            "UPDATE tickets SET no_empleado_asignado = ?, fecha_actualizacion = NOW() WHERE id = ?"
        );
        $stmt->bind_param('si', $asignado, $id);
        $ok = $stmt->execute();
        $stmt->close();
        responder($ok, $ok ? '' : 'Error al asignar ticket.');
    }

    // ── CERRAR TICKET ──────────────────────────────────────────────────────────
    case 'cerrarTicket': {
        requiereBiJson($conn, $noEmpSesion);
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) responder(false, 'ID inválido.');

        $stmt = $conn->prepare(
            "UPDATE tickets SET estado = 'cerrado', fecha_cierre = NOW(), fecha_actualizacion = NOW() WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        responder($ok, $ok ? '' : 'Error al cerrar ticket.');
    }

    // ── OBTENER ESTADÍSTICAS ───────────────────────────────────────────────────
    case 'obtenerEstadisticas': {
        // es_bi se deriva del servidor — ignorar lo que mande el cliente
        $esBi       = $esBiSesion ? 1 : 0;
        $fechaDesde = $_POST['fecha_desde'] ?? '';
        $fechaHasta = $_POST['fecha_hasta'] ?? '';
        $idCat      = (int)($_POST['id_categoria'] ?? 0);
        $modoRep    = (int)($_POST['modo_reporte'] ?? 0);

        // Armar WHERE con placeholders (prepared statement; evita SQL injection)
        $where  = ['1=1'];
        $types  = '';
        $params = [];

        if (!$esBi) {
            $where[] = 't.no_empleado_solicitante = ?';
            $types  .= 's';
            $params[] = $noEmpleado;
        }
        if ($fechaDesde) {
            $where[] = 'DATE(t.fecha_creacion) >= ?';
            $types  .= 's';
            $params[] = $fechaDesde;
        }
        if ($fechaHasta) {
            $where[] = 'DATE(t.fecha_creacion) <= ?';
            $types  .= 's';
            $params[] = $fechaHasta;
        }
        if ($idCat) {
            $where[] = 't.id_categoria = ?';
            $types  .= 'i';
            $params[] = $idCat;
        }
        $whereStr = implode(' AND ', $where);
        $base = "FROM tickets t LEFT JOIN tickets_categorias c ON t.id_categoria = c.id WHERE $whereStr";

        $ejecutar = function (string $sql, string $types, array $params) use ($conn): array {
            $stmt = $conn->prepare($sql);
            if (!$stmt) return [];
            if ($types !== '') $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            while ($r = $res->fetch_assoc()) $rows[] = $r;
            $stmt->close();
            return $rows;
        };

        // Counts totales
        $row = $ejecutar(
            "SELECT
                COUNT(*) total,
                SUM(estado='nuevo') nuevos,
                SUM(estado='en_proceso') en_proceso,
                SUM(estado='resuelto') resueltos,
                SUM(estado='cerrado') cerrados,
                ROUND(AVG(CASE WHEN fecha_cierre IS NOT NULL THEN DATEDIFF(fecha_cierre,fecha_creacion) END),1) promedio_dias
            $base",
            $types, $params
        );
        $row = $row[0] ?? [];

        // Por categoría
        $cats = $ejecutar(
            "SELECT c.nombre, COUNT(*) total $base GROUP BY t.id_categoria, c.nombre ORDER BY total DESC LIMIT 10",
            $types, $params
        );

        // Por mes (año actual)
        $meses = $ejecutar(
            "SELECT DATE_FORMAT(t.fecha_creacion,'%b %Y') mes,
                    MONTH(t.fecha_creacion) nmes, COUNT(*) total
             $base AND YEAR(t.fecha_creacion) = ?
             GROUP BY nmes, mes ORDER BY nmes",
            $types . 'i', array_merge($params, [(int)date('Y')])
        );

        $datos = array_merge((array)$row, [
            'categorias' => $cats,
            'meses'      => $meses,
        ]);

        if ($modoRep) {
            $datos['agentes'] = $ejecutar(
                "SELECT COALESCE(t.no_empleado_asignado,'Sin asignar') agente, COUNT(*) total
                 $base GROUP BY agente ORDER BY total DESC LIMIT 10",
                $types, $params
            );
            $datos['prioridades'] = $ejecutar(
                "SELECT prioridad, COUNT(*) total $base GROUP BY prioridad ORDER BY total DESC",
                $types, $params
            );
        }

        responder(true, '', ['datos' => $datos]);
    }

    default:
        responder(false, 'Acción no reconocida.');
}
