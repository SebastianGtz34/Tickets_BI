<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'conn.php';
require_once 'auth.php';
require_once 'acciones_notificaciones.php';

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
    $sql  = "SELECT MAX(id) AS id FROM tickets WHERE YEAR(fecha_creacion) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $anio);
    $stmt->execute();
    $n = (int)$stmt->get_result()->fetch_assoc()['id'] + 1;
    $stmt->close();
    return sprintf('TKT-%d-%03d', $anio, $n);
}

/**
 * Lista de staff activo: BI+TI (deptos 27 y 39) o, si se pasa $depto, solo ese
 * departamento. Se usa para el select de asignación, que se acota al depto del ticket.
 * Devuelve [{noEmpleado, nombre}, …].
 */
function tkObtenerMiembrosBi(mysqli $conn, ?int $depto = null): array {
    if ($depto !== null) {
        $stmt = $conn->prepare(
            "SELECT noEmpleado, nombre FROM mess_rrhh.usuarios
             WHERE departamento = ? AND estatus = 1
             ORDER BY nombre ASC"
        );
        $stmt->bind_param('i', $depto);
        $stmt->execute();
        $res = $stmt->get_result();
        $list = [];
        while ($r = $res->fetch_assoc()) $list[] = $r;
        $stmt->close();
        return $list;
    }
    $res = $conn->query(
        "SELECT noEmpleado, nombre FROM mess_rrhh.usuarios
         WHERE departamento IN (27, 39) AND estatus = 1
         ORDER BY nombre ASC"
    );
    $list = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) $list[] = $r;
    }
    return $list;
}

/** Asignados de un ticket: [{no_empleado, nombre}, …]. */
function tkObtenerAsignados(mysqli $conn, int $idTicket): array {
    $stmt = $conn->prepare(
        "SELECT ta.no_empleado, u.nombre
         FROM tickets_asignados ta
         LEFT JOIN mess_rrhh.usuarios u ON u.noEmpleado = ta.no_empleado
         WHERE ta.id_ticket = ?
         ORDER BY ta.fecha_asignacion ASC"
    );
    $stmt->bind_param('i', $idTicket);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    $stmt->close();
    return $out;
}

/**
 * Filtra una lista de noEmpleado dejando solo staff activo (deptos 27, 39);
 * si se pasa $depto, restringe a ese único departamento (separación BI/TI).
 */
function tkFiltrarSoloBi(mysqli $conn, array $noEmps, ?int $depto = null): array {
    $noEmps = array_values(array_unique(array_filter(array_map('intval', $noEmps))));
    if (!$noEmps) return [];
    $ph = implode(',', array_fill(0, count($noEmps), '?'));
    if ($depto !== null) {
        $sql    = "SELECT noEmpleado FROM mess_rrhh.usuarios
                   WHERE departamento = ? AND estatus = 1 AND noEmpleado IN ($ph)";
        $types  = 'i' . str_repeat('i', count($noEmps));
        $params = array_merge([$depto], $noEmps);
    } else {
        $sql    = "SELECT noEmpleado FROM mess_rrhh.usuarios
                   WHERE departamento IN (27, 39) AND estatus = 1 AND noEmpleado IN ($ph)";
        $types  = str_repeat('i', count($noEmps));
        $params = $noEmps;
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $validos = [];
    while ($r = $res->fetch_assoc()) $validos[] = (int)$r['noEmpleado'];
    $stmt->close();
    return $validos;
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
        // La descripción no puede ser solo espacios; mínimo 10 caracteres reales.
        if (mb_strlen($descripcion) < 10) {
            responder(false, 'La descripción debe tener al menos 10 caracteres (no solo espacios).');
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

        // Notificar a BI del nuevo ticket
        tkNotificarNuevoTicket($conn, $idTicket, (int)$noEmpleado, $folio, $titulo);

        responder(true, 'Ticket creado.', ['folio' => $folio, 'id' => $idTicket]);
    }

    // ── OBTENER TICKETS ───────────────────────────────────────────────────────
    case 'obtenerTickets': {
        $esBi              = $esBiSesion ? 1 : 0;
        $deptoParam        = trim($_POST['departamento'] ?? '');
        $filtroCatDepto    = (int)($_POST['filtro_categoria_depto'] ?? 0);
        $estado            = $_POST['estado']     ?? '';
        $prioridad         = $_POST['prioridad']  ?? '';
        $fechaDesde        = $_POST['fecha_desde'] ?? '';
        $fechaHasta        = $_POST['fecha_hasta'] ?? '';
        $soloAsig          = (int)($_POST['solo_asignado'] ?? 0);
        $limite            = (int)($_POST['limite'] ?? 0);

        $where = ['1=1'];
        $params = [];
        $types  = '';

        if ($filtroCatDepto && $esBi) {
            // Bandeja: BI ve categorías sistema/otro, TI ve categorías ti
            $deptoSesion = obtenerNombreDepto($conn, $noEmpSesion) ?: 'bi';
            if ($deptoSesion === 'ti') {
                $where[] = 'c.tipo = ?';
                $params[] = 'ti';
                $types   .= 's';
            } else {
                $where[] = "c.tipo IN ('sistema','otro')";
            }
        } else {
            // Mis tickets: no-BI/TI siempre filtrado; BI/TI filtrado cuando la vista
            // envía departamento (señal de que es contexto mis_tickets, no bandeja manual)
            if (!$esBi || $deptoParam !== '') {
                $where[] = 't.no_empleado_solicitante = ?';
                $params[] = $noEmpleado;
                $types   .= 's';
            }
        }
        if ($soloAsig && $esBi) {
            $where[] = 'EXISTS (SELECT 1 FROM tickets_asignados ta_f WHERE ta_f.id_ticket = t.id AND ta_f.no_empleado = ?)';
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
        $sql = "SELECT t.*, c.nombre AS categoria, c.tipo AS categoria_tipo,
                       COALESCE(us.nombre, CONCAT('Empleado #', t.no_empleado_solicitante)) AS nombre_solicitante,
                       (SELECT GROUP_CONCAT(ta.no_empleado SEPARATOR ',')
                          FROM tickets_asignados ta WHERE ta.id_ticket = t.id) AS asignados_ids,
                       (SELECT GROUP_CONCAT(COALESCE(u.nombre, CONCAT('Empleado #', ta.no_empleado)) SEPARATOR ', ')
                          FROM tickets_asignados ta
                          LEFT JOIN mess_rrhh.usuarios u ON u.noEmpleado = ta.no_empleado
                          WHERE ta.id_ticket = t.id) AS asignados_nombres
                FROM tickets t
                LEFT JOIN tickets_categorias c ON t.id_categoria = c.id
                LEFT JOIN mess_rrhh.usuarios us ON us.noEmpleado = t.no_empleado_solicitante
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
            "SELECT t.*, c.nombre AS categoria, c.tipo AS categoria_tipo,
                    COALESCE(us.nombre, CONCAT('Empleado #', t.no_empleado_solicitante)) AS nombre_solicitante
             FROM tickets t
             LEFT JOIN tickets_categorias c ON t.id_categoria = c.id
             LEFT JOIN mess_rrhh.usuarios us ON us.noEmpleado = t.no_empleado_solicitante
             WHERE t.id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ticket = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ticket) responder(false, 'Ticket no encontrado.');

        // No-BI/TI: puede ver si creó el ticket o si fue mencionado en algún comentario
        if (!$esBiSesion && (string)$ticket['no_empleado_solicitante'] !== (string)$noEmpleado) {
            $chkMenc = $conn->prepare(
                "SELECT 1 FROM tickets_comentarios WHERE id_ticket = ? AND FIND_IN_SET(?, menciones) LIMIT 1"
            );
            $chkMenc->bind_param('is', $id, $noEmpleado);
            $chkMenc->execute();
            $esMencionado = $chkMenc->get_result()->num_rows > 0;
            $chkMenc->close();
            if (!$esMencionado) responder(false, 'No tienes permiso para ver este ticket.');
        }

        // Asignados (puede haber hasta 3)
        $ticket['asignados'] = tkObtenerAsignados($conn, $id);

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
        $validos = ['nuevo','en_proceso','pendiente','resuelto','cerrado','cancelado'];
        if (!$id || !in_array($estado, $validos, true)) responder(false, 'Datos inválidos.');
        if (!puedeGestionarTicket($conn, $noEmpSesion, $id)) responder(false, 'Este ticket pertenece a otro departamento.');

        // Sellado de fechas según el estado destino:
        //  - 'resuelto'  → sella fecha_resuelto (base del auto-cierre a 3 días).
        //  - 'cancelado' → estado terminal: sella fecha_cierre y limpia fecha_resuelto.
        //  - cualquier otro estado abierto → limpia fecha_resuelto.
        if ($estado === 'resuelto') {
            $stmt = $conn->prepare(
                "UPDATE tickets SET estado = ?, fecha_resuelto = NOW(), fecha_actualizacion = NOW() WHERE id = ?"
            );
        } elseif ($estado === 'cancelado') {
            $stmt = $conn->prepare(
                "UPDATE tickets SET estado = ?, fecha_resuelto = NULL, fecha_cierre = NOW(), fecha_actualizacion = NOW() WHERE id = ?"
            );
        } else {
            $stmt = $conn->prepare(
                "UPDATE tickets SET estado = ?, fecha_resuelto = NULL, fecha_actualizacion = NOW() WHERE id = ?"
            );
        }
        $stmt->bind_param('si', $estado, $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            tkNotificarCambioEstado($conn, $id, $estado, (int)$noEmpleado);
        }
        responder($ok, $ok ? '' : 'Error al actualizar estado.');
    }

    // ── ASIGNAR TICKET (hasta 3 ingenieros BI) ────────────────────────────────
    case 'asignarTicket': {
        requiereBiJson($conn, $noEmpSesion);
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) responder(false, 'ID inválido.');
        if (!puedeGestionarTicket($conn, $noEmpSesion, $id)) responder(false, 'Este ticket pertenece a otro departamento.');

        // Acepta array (asignados[]) o string CSV ('523,177,45'). Vacío = limpiar asignación.
        $raw = $_POST['asignados'] ?? [];
        if (is_string($raw)) {
            $raw = $raw !== '' ? array_map('trim', explode(',', $raw)) : [];
        }
        $solicitados = array_values(array_unique(array_filter(array_map('intval', $raw))));

        if (count($solicitados) > 3) {
            responder(false, 'Máximo 3 ingenieros por ticket.');
        }

        // Validar que TODOS sean staff activo del departamento dueño del ticket.
        if ($solicitados) {
            $deptoTicket = ticketDepartamento($conn, $id);
            $validos     = tkFiltrarSoloBi($conn, $solicitados, $deptoTicket);
            $invalidos   = array_diff($solicitados, $validos);
            if ($invalidos) {
                responder(false, 'No pertenecen al departamento del ticket: ' . implode(', ', $invalidos));
            }
        }

        // Asignados previos (para calcular nuevos y notificar solo a esos)
        $previos = array_map(fn($a) => (int)$a['no_empleado'], tkObtenerAsignados($conn, $id));

        $conn->begin_transaction();
        try {
            $del = $conn->prepare("DELETE FROM tickets_asignados WHERE id_ticket = ?");
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();

            if ($solicitados) {
                $ins = $conn->prepare(
                    "INSERT INTO tickets_asignados (id_ticket, no_empleado, fecha_asignacion)
                     VALUES (?, ?, NOW())"
                );
                foreach ($solicitados as $emp) {
                    $empStr = (string)$emp;
                    $ins->bind_param('is', $id, $empStr);
                    $ins->execute();
                }
                $ins->close();
            }

            $upd = $conn->prepare("UPDATE tickets SET fecha_actualizacion = NOW() WHERE id = ?");
            $upd->bind_param('i', $id);
            $upd->execute();
            $upd->close();

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            responder(false, 'Error al asignar ticket.');
        }

        // Notificar SOLO a los nuevos (los que no estaban antes)
        $nuevos = array_values(array_diff($solicitados, $previos));
        if ($nuevos) {
            tkNotificarAsignacion($conn, $id, $nuevos, (int)$noEmpleado);
        }

        responder(true, 'Asignación actualizada.', ['asignados' => tkObtenerAsignados($conn, $id)]);
    }

    // ── OBTENER EQUIPO (para el select de asignación) ─────────────────────────
    case 'obtenerEquipoBi': {
        requiereBiJson($conn, $noEmpSesion);
        // Asignables = solo el departamento dueño del ticket (separación BI/TI).
        // Sin id se devuelve el equipo combinado (compatibilidad).
        $id    = (int)($_POST['id'] ?? 0);
        $depto = $id ? ticketDepartamento($conn, $id) : null;
        responder(true, '', ['miembros' => tkObtenerMiembrosBi($conn, $depto)]);
    }

    // ── CERRAR TICKET ──────────────────────────────────────────────────────────
    case 'cerrarTicket': {
        requiereBiJson($conn, $noEmpSesion);
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) responder(false, 'ID inválido.');
        if (!puedeGestionarTicket($conn, $noEmpSesion, $id)) responder(false, 'Este ticket pertenece a otro departamento.');

        $stmt = $conn->prepare(
            "UPDATE tickets SET estado = 'cerrado', fecha_cierre = NOW(), fecha_actualizacion = NOW() WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            tkNotificarCambioEstado($conn, $id, 'cerrado', (int)$noEmpleado);
        }
        responder($ok, $ok ? '' : 'Error al cerrar ticket.');
    }

    // ── CANCELAR TICKET (por el propio solicitante) ─────────────────────────────
    // No requiere rol BI: cualquier usuario puede cancelar SU PROPIO ticket. En la
    // BD solo cambia a estado 'cancelado' (terminal); la propiedad se verifica
    // SIEMPRE contra el no_empleado de la sesión, nunca contra un parámetro cliente.
    case 'cancelarTicketUsuario': {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) responder(false, 'ID inválido.');

        $stmt = $conn->prepare(
            "SELECT estado, no_empleado_solicitante FROM tickets WHERE id = ?"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) responder(false, 'Ticket no encontrado.');
        if ((string)$row['no_empleado_solicitante'] !== (string)$noEmpleado) {
            responder(false, 'Solo puedes cancelar tickets que tú creaste.');
        }
        if (in_array($row['estado'], ['cerrado', 'cancelado'], true)) {
            responder(false, 'Este ticket ya está ' . ($row['estado'] === 'cancelado' ? 'cancelado' : 'cerrado') . '.');
        }

        // Estado terminal: sella fecha_cierre y limpia fecha_resuelto (mismo sellado
        // que la cancelación de staff en actualizarEstado).
        $stmt = $conn->prepare(
            "UPDATE tickets
                SET estado = 'cancelado', fecha_resuelto = NULL, fecha_cierre = NOW(), fecha_actualizacion = NOW()
              WHERE id = ? AND no_empleado_solicitante = ?"
        );
        $stmt->bind_param('is', $id, $noEmpleado);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            tkNotificarCancelacionUsuario($conn, $id, (int)$noEmpleado);
        }
        responder($ok, $ok ? 'Ticket cancelado.' : 'Error al cancelar el ticket.');
    }

    // ── OBTENER ESTADÍSTICAS ───────────────────────────────────────────────────
    case 'obtenerEstadisticas': {
        // es_bi se deriva del servidor — ignorar lo que mande el cliente
        $esBi       = $esBiSesion ? 1 : 0;
        $depto      = (int)($_POST['departamento'] ?? 0);
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
        } elseif ($depto) {
            // Filtrar por departamento del solicitante
            $where[] = "t.no_empleado_solicitante IN (SELECT noEmpleado FROM mess_rrhh.usuarios WHERE departamento = ?)";
            $types  .= 'i';
            $params[] = $depto;
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
                SUM(estado='cancelado') cancelados,
                ROUND(AVG(CASE WHEN fecha_cierre   IS NOT NULL THEN DATEDIFF(fecha_cierre,  fecha_creacion) END),1) promedio_cierre,
                ROUND(AVG(CASE WHEN fecha_resuelto IS NOT NULL THEN DATEDIFF(fecha_resuelto,fecha_creacion) END),1) promedio_resolucion
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
            // Agentes: ahora viven en tickets_asignados. Un ticket con N asignados cuenta N
            // veces (carga por agente). Tickets sin asignar cuentan como 'Sin asignar'.
            $datos['agentes'] = $ejecutar(
                "SELECT COALESCE(u.nombre, CONCAT('Empleado #', ta.no_empleado), 'Sin asignar') agente, COUNT(*) total
                 FROM tickets t
                 LEFT JOIN tickets_categorias c ON t.id_categoria = c.id
                 LEFT JOIN tickets_asignados ta ON ta.id_ticket = t.id
                 LEFT JOIN mess_rrhh.usuarios u ON u.noEmpleado = ta.no_empleado
                 WHERE $whereStr
                 GROUP BY agente ORDER BY total DESC LIMIT 10",
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
