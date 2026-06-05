<?php
/**
 * Notificaciones de Tickets BI.
 *
 * Escribe en mess_rrhh.notificacion_historial con sistema = 'ticketsBI'.
 *
 * Receptores del lado BI:
 *   - Si el ticket tiene ingenieros asignados (1..3): a esos.
 *   - Si no hay asignados: todo el equipo BI (mess_rrhh.usuarios
 *     WHERE departamento = 32 AND estatus = 1).
 *
 * Eventos dirigidos al solicitante:
 *   - CambioEstadoTicket     → cuando BI mueve el estado.
 *   - AsignacionIngeniero    → cuando BI asigna o reasigna.
 *   - ComentarioBI           → cuando BI agrega comentario público.
 *
 * Eventos dirigidos al equipo BI:
 *   - NuevoTicket            → al crearse.
 *   - ComentarioUsuario      → cuando el solicitante comenta.
 *   - NotaInternaTicket      → cuando BI marca una nota interna (solo entre BI).
 *   - TicketEstancado        → cron: >= 3 días sin cambio de estado.
 *   - TicketSinAsignar       → cron: >= 2 días sin ingeniero asignado.
 *
 * Las notificaciones de cron usan dedup por
 * (destino + sistema + accion + id_registro_referencia) entre las NoLeida.
 */

if (!function_exists('tkNotifEquipoBi')) {

    /** Lista de noEmpleado del equipo BI (usuarios activos del depto 32). */
    function tkNotifEquipoBi(mysqli $conn): array {
        $res = $conn->query(
            "SELECT noEmpleado FROM mess_rrhh.usuarios
             WHERE departamento = 32 AND estatus = 1"
        );
        $ids = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $ids[] = (int)$r['noEmpleado'];
        }
        return $ids;
    }

    /**
     * Receptores BI para un ticket: asignados del ticket, o todo BI si no hay.
     * Excluye opcionalmente a $excepto.
     */
    function tkNotifReceptoresBi(mysqli $conn, int $idTicket, ?int $excepto = null): array {
        $stmt = $conn->prepare("SELECT no_empleado FROM tickets_asignados WHERE id_ticket = ?");
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $res = $stmt->get_result();
        $destinos = [];
        while ($r = $res->fetch_assoc()) $destinos[] = (int)$r['no_empleado'];
        $stmt->close();

        if (!$destinos) $destinos = tkNotifEquipoBi($conn);
        if ($excepto !== null) {
            $destinos = array_values(array_filter($destinos, fn($x) => $x !== $excepto));
        }
        return array_values(array_unique($destinos));
    }

    /**
     * Usuarios que se pueden @mencionar: cualquier empleado activo de mess_rrhh.
     * Marca es_bi=1 si pertenece al equipo BI (depto 32) para decidir la pantalla
     * destino de la notificación y la restricción de notas internas.
     * Devuelve [{noEmpleado:int, nombre:string, es_bi:int}, …].
     */
    function tkMencionables(mysqli $conn): array {
        $out = [];
        $res = $conn->query(
            "SELECT noEmpleado, nombre, departamento FROM mess_rrhh.usuarios
             WHERE estatus = 1
             ORDER BY nombre ASC"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $id = (int)$r['noEmpleado'];
                $out[] = [
                    'noEmpleado' => $id,
                    'nombre'     => $r['nombre'] ?: ('Empleado #' . $id),
                    'es_bi'      => ((int)$r['departamento'] === 32) ? 1 : 0,
                ];
            }
        }
        return $out;
    }

    /**
     * Pantalla destino para un destinatario en modo lectura (solicitante o mencionado no-gestor):
     * BI → `ver_ticket` (página completa, requiere BI); no-BI → `embed_ver` (vista slim, solo sesión).
     * Evita que un usuario fuera del depto 32 caiga en un dead-end al abrir la notificación.
     */
    function tkArchivoLectura(mysqli $conn, int $noEmp): string {
        static $bi = null;
        if ($bi === null) $bi = array_flip(tkNotifEquipoBi($conn));
        return isset($bi[$noEmp]) ? 'ver_ticket' : 'embed_ver';
    }

    /**
     * INSERT en notificacion_historial. Devuelve true si se insertó.
     * Si $dedup=true, no inserta cuando ya exista una NoLeida con
     * (destino + ticketsBI + accion + id_registro_referencia).
     */
    function tkNotifInsertar(
        mysqli $conn,
        int $actualiza,
        int $destino,
        string $accion,
        string $archivo,
        int $idRegistro,
        string $recordar,
        bool $dedup = false
    ): bool {
        if ($destino === $actualiza) return false;

        if ($dedup) {
            $chk = $conn->prepare(
                "SELECT 1 FROM mess_rrhh.notificacion_historial
                 WHERE id_usuario_destino = ? AND sistema = 'ticketsBI'
                   AND accion = ? AND id_registro_referencia = ? AND estatus = 'NoLeida'
                 LIMIT 1"
            );
            $chk->bind_param('isi', $destino, $accion, $idRegistro);
            $chk->execute();
            $existe = $chk->get_result()->num_rows > 0;
            $chk->close();
            if ($existe) return false;
        }

        $stmt = $conn->prepare(
            "INSERT INTO mess_rrhh.notificacion_historial
                (id_usuario_actualiza, id_usuario_destino, accion, sistema, archivo,
                 id_registro_referencia, fecha_creacion, fecha_atencion, recordar, estatus)
             VALUES (?, ?, ?, 'ticketsBI', ?, ?, NOW(), NULL, ?, 'NoLeida')"
        );
        $stmt->bind_param('iissis', $actualiza, $destino, $accion, $archivo, $idRegistro, $recordar);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ── Eventos directos ─────────────────────────────────────────────────────

    function tkNotificarNuevoTicket(mysqli $conn, int $idTicket, int $solicitante, string $folio, string $titulo): void {
        $destinos = tkNotifReceptoresBi($conn, $idTicket, $solicitante);
        $recordar = "Nuevo ticket {$folio}: " . mb_substr($titulo, 0, 80);
        foreach ($destinos as $d) {
            tkNotifInsertar($conn, $solicitante, $d, 'NuevoTicket', 'gestionar_ticket', $idTicket, $recordar);
        }
    }

    function tkNotificarCambioEstado(mysqli $conn, int $idTicket, string $nuevoEstado, int $actualizadoPor): void {
        $stmt = $conn->prepare("SELECT folio, no_empleado_solicitante FROM tickets WHERE id = ?");
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return;

        $solicitante = (int)$row['no_empleado_solicitante'];
        $etiqueta    = ucfirst(str_replace('_', ' ', $nuevoEstado));

        // Solicitante
        if ($solicitante !== $actualizadoPor) {
            tkNotifInsertar($conn, $actualizadoPor, $solicitante, 'CambioEstadoTicket', tkArchivoLectura($conn, $solicitante), $idTicket,
                "Tu ticket {$row['folio']} cambió a \"{$etiqueta}\"");
        }
        // Otros asignados (compañeros)
        foreach (tkNotifReceptoresBi($conn, $idTicket, $actualizadoPor) as $d) {
            if ($d === $solicitante) continue;
            tkNotifInsertar($conn, $actualizadoPor, $d, 'CambioEstadoTicket', 'gestionar_ticket', $idTicket,
                "Estado de {$row['folio']} actualizado a \"{$etiqueta}\"");
        }
    }

    /** $nuevosAsignados: array con los noEmpleado que se acaban de agregar (no los previos). */
    function tkNotificarAsignacion(mysqli $conn, int $idTicket, array $nuevosAsignados, int $actualizadoPor): void {
        if (!$nuevosAsignados) return;

        // Folio + solicitante + nombres
        $ph = implode(',', array_fill(0, count($nuevosAsignados), '?'));
        $stmt = $conn->prepare(
            "SELECT t.folio, t.no_empleado_solicitante,
                    GROUP_CONCAT(COALESCE(u.nombre, CONCAT('Empleado #', ta.no_empleado)) SEPARATOR ', ') AS nombres
             FROM tickets t
             LEFT JOIN tickets_asignados ta ON ta.id_ticket = t.id AND ta.no_empleado IN ($ph)
             LEFT JOIN mess_rrhh.usuarios u ON u.noEmpleado = ta.no_empleado
             WHERE t.id = ?
             GROUP BY t.id, t.folio, t.no_empleado_solicitante"
        );
        $params = array_merge(array_map('intval', $nuevosAsignados), [$idTicket]);
        $types  = str_repeat('i', count($nuevosAsignados) + 1);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return;

        $solicitante = (int)$row['no_empleado_solicitante'];
        $nombres     = $row['nombres'] ?: 'el equipo BI';

        // Solicitante: entera quién atiende su ticket.
        tkNotifInsertar(
            $conn, $actualizadoPor, $solicitante, 'AsignacionIngeniero', tkArchivoLectura($conn, $solicitante), $idTicket,
            "Tu ticket {$row['folio']} fue asignado a {$nombres}"
        );

        // Cada nuevo asignado: sabe que le tocó.
        foreach ($nuevosAsignados as $emp) {
            tkNotifInsertar(
                $conn, $actualizadoPor, (int)$emp, 'AsignacionIngeniero', 'gestionar_ticket', $idTicket,
                "Te asignaron el ticket {$row['folio']}"
            );
        }
    }

    function tkNotificarComentario(
        mysqli $conn,
        int $idTicket,
        int $idComentario,
        string $texto,
        int $autor,
        bool $esInterno,
        bool $autorEsBi
    ): void {
        $stmt = $conn->prepare("SELECT folio, no_empleado_solicitante FROM tickets WHERE id = ?");
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return;

        $solicitante = (int)$row['no_empleado_solicitante'];
        $extracto    = mb_substr($texto, 0, 120);
        $extracto    = $extracto !== '' ? ": \"{$extracto}\"" : '';

        // Notas internas: solo entre BI.
        if ($esInterno) {
            foreach (tkNotifReceptoresBi($conn, $idTicket, $autor) as $d) {
                tkNotifInsertar($conn, $autor, $d, 'NotaInternaTicket', 'gestionar_ticket', $idTicket,
                    "Nueva nota interna en {$row['folio']}{$extracto}");
            }
            return;
        }

        if ($autorEsBi) {
            // Comentario público de BI → al solicitante + a los otros asignados del ticket.
            tkNotifInsertar($conn, $autor, $solicitante, 'ComentarioBI', tkArchivoLectura($conn, $solicitante), $idTicket,
                "BI comentó tu ticket {$row['folio']}{$extracto}");
            foreach (tkNotifReceptoresBi($conn, $idTicket, $autor) as $d) {
                if ($d === $solicitante) continue;
                tkNotifInsertar($conn, $autor, $d, 'ComentarioBI', 'gestionar_ticket', $idTicket,
                    "Nuevo comentario en {$row['folio']}{$extracto}");
            }
        } else {
            // Comentario del solicitante → asignados (o todo BI si no hay).
            foreach (tkNotifReceptoresBi($conn, $idTicket, $autor) as $d) {
                tkNotifInsertar($conn, $autor, $d, 'ComentarioUsuario', 'gestionar_ticket', $idTicket,
                    "El solicitante comentó en {$row['folio']}{$extracto}");
            }
        }
    }

    /**
     * Notifica a los usuarios @mencionados en un comentario.
     * $mencionados: array de noEmpleado (ya validados como mencionables del ticket).
     * El destino que sea BI llega a gestionar_ticket; el resto a ver_ticket.
     */
    function tkNotificarMencion(mysqli $conn, int $idTicket, string $texto, int $autor, array $mencionados): void {
        if (!$mencionados) return;

        $stmt = $conn->prepare("SELECT folio FROM tickets WHERE id = ?");
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return;
        $folio = $row['folio'];

        // Nombre del autor para el texto del toast.
        $nombreAutor = 'Alguien';
        $st = $conn->prepare("SELECT nombre FROM mess_rrhh.usuarios WHERE noEmpleado = ?");
        $st->bind_param('i', $autor);
        $st->execute();
        $ra = $st->get_result()->fetch_assoc();
        $st->close();
        if ($ra && $ra['nombre']) $nombreAutor = $ra['nombre'];

        // Para decidir la pantalla destino según el rol del mencionado.
        $biSet = array_flip(tkNotifEquipoBi($conn));

        $extracto = mb_substr($texto, 0, 100);
        $extracto = $extracto !== '' ? ": \"{$extracto}\"" : '';

        foreach ($mencionados as $m) {
            $m = (int)$m;
            $archivo = isset($biSet[$m]) ? 'gestionar_ticket' : 'embed_ver';
            tkNotifInsertar($conn, $autor, $m, 'MencionComentario', $archivo, $idTicket,
                "{$nombreAutor} te mencionó en {$folio}{$extracto}");
        }
    }

    // ── Crons (con dedup) ────────────────────────────────────────────────────

    /** Tickets no cerrados con >=3 días sin actualizarse. id_usuario_actualiza=523 (sistema). */
    function tkNotifCronEstancados(mysqli $conn): int {
        $res = $conn->query(
            "SELECT id, folio
             FROM tickets
             WHERE estado != 'cerrado'
               AND DATEDIFF(NOW(), fecha_actualizacion) >= 3"
        );
        $generadas = 0;
        if (!$res) return 0;
        while ($t = $res->fetch_assoc()) {
            $destinos = tkNotifReceptoresBi($conn, (int)$t['id'], null);
            $recordar = "Sin movimiento: {$t['folio']} lleva 3+ días sin cambio";
            foreach ($destinos as $d) {
                if (tkNotifInsertar($conn, 523, $d, 'TicketEstancado', 'gestionar_ticket', (int)$t['id'], $recordar, true)) {
                    $generadas++;
                }
            }
        }
        return $generadas;
    }

    /** Tickets no cerrados con >=2 días desde creación y SIN asignación. Notifica a todo BI. */
    function tkNotifCronSinAsignar(mysqli $conn): int {
        $res = $conn->query(
            "SELECT id, folio
             FROM tickets
             WHERE estado != 'cerrado'
               AND DATEDIFF(NOW(), fecha_creacion) >= 2
               AND NOT EXISTS (SELECT 1 FROM tickets_asignados ta WHERE ta.id_ticket = tickets.id)"
        );
        $generadas = 0;
        if (!$res) return 0;
        $bi = tkNotifEquipoBi($conn);
        while ($t = $res->fetch_assoc()) {
            $recordar = "Sin asignar: {$t['folio']} lleva 2+ días esperando ingeniero";
            foreach ($bi as $d) {
                if (tkNotifInsertar($conn, 523, $d, 'TicketSinAsignar', 'gestionar_ticket', (int)$t['id'], $recordar, true)) {
                    $generadas++;
                }
            }
        }
        return $generadas;
    }

    /**
     * Auto-cierre: tickets en estado 'resuelto' con >=3 días desde fecha_resuelto
     * se marcan como 'cerrado'. Notifica al solicitante y asignados (id_usuario_actualiza=523).
     * Devuelve el número de tickets cerrados.
     */
    function tkCronCerrarResueltos(mysqli $conn): int {
        $res = $conn->query(
            "SELECT id, folio FROM tickets
             WHERE estado = 'resuelto'
               AND fecha_resuelto IS NOT NULL
               AND DATEDIFF(NOW(), fecha_resuelto) >= 3"
        );
        if (!$res) return 0;

        $cerrados = 0;
        $upd = $conn->prepare(
            "UPDATE tickets SET estado = 'cerrado', fecha_cierre = NOW(), fecha_actualizacion = NOW() WHERE id = ?"
        );
        while ($t = $res->fetch_assoc()) {
            $id = (int)$t['id'];
            $upd->bind_param('i', $id);
            if ($upd->execute()) {
                $cerrados++;
                // Una vez en 'cerrado' ya no vuelve a entrar al query → sin duplicar notificación.
                tkNotificarCambioEstado($conn, $id, 'cerrado', 523);
            }
        }
        $upd->close();
        return $cerrados;
    }
}

// ── Router cuando se invoca directo (endpoint AJAX) ──────────────────────────
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/conn.php';
    require_once __DIR__ . '/auth.php';

    $noEmpSesion = requiereSesionJson();
    $accion = $_POST['accion'] ?? '';

    switch ($accion) {
        case 'ejecutarCronTicketsBI': {
            // Solo BI puede gatillar los chequeos.
            requiereBiJson($conn, $noEmpSesion);
            $a = tkNotifCronEstancados($conn);
            $b = tkNotifCronSinAsignar($conn);
            $c = tkCronCerrarResueltos($conn);
            echo json_encode([
                'success' => true,
                'generadas_estancados'  => $a,
                'generadas_sin_asignar' => $b,
                'cerrados_automaticos'  => $c
            ]);
            exit;
        }
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
            exit;
    }
}
