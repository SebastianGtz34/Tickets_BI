<?php
/**
 * Notificaciones de Tickets BI/TI.
 *
 * Escribe en mess_rrhh.notificacion_historial con sistema = 'ticketsBI'.
 *
 * Receptores del lado BI/TI:
 *   - Si el ticket tiene ingenieros asignados (1..3): a esos.
 *   - Si no hay asignados: solo el departamento DUEÑO del ticket, no ambos.
 *     El dueño se deriva del tipo de categoría (auth.php ticketDepartamento): tipo
 *     'ti' → TI (39), 'sistema'/'otro' → BI (27). Así BI no recibe tickets de TI
 *     ni viceversa.
 *
 * Eventos dirigidos al solicitante:
 *   - CambioEstadoTicket     → cuando BI/TI mueve el estado.
 *   - AsignacionIngeniero    → cuando BI/TI asigna o reasigna.
 *   - ComentarioBI           → cuando BI/TI agrega comentario público.
 *
 * Eventos dirigidos al equipo BI/TI:
 *   - NuevoTicket            → al crearse. ÚNICO evento que además manda correo
 *                              (a los mismos destinos), vía includes/correo.php.
 *   - ComentarioUsuario      → cuando el solicitante comenta.
 *   - NotaInternaTicket      → cuando BI/TI marca una nota interna (solo entre BI/TI).
 *   - TicketEstancado        → cron: >= 3 días sin cambio de estado.
 *   - TicketSinAsignar       → cron: >= 2 días sin ingeniero asignado.
 *
 * Las notificaciones de cron usan dedup por
 * (destino + sistema + accion + id_registro_referencia) entre las NoLeida.
 */

if (!function_exists('tkNotifEquipoBi')) {

    /**
     * Lista de noEmpleado del equipo BI+TI (usuarios activos de deptos 27, 39).
     * Para receptores de notificación usa tkNotifReceptoresBi (segmenta por depto);
     * esta función se reserva para saber si un empleado es staff (mención / pantalla destino).
     */
    function tkNotifEquipoBi(mysqli $conn): array {
        $res = $conn->query(
            "SELECT noEmpleado FROM mess_rrhh.usuarios
             WHERE departamento IN (27, 39) AND estatus = 1"
        );
        $ids = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) $ids[] = (int)$r['noEmpleado'];
        }
        return $ids;
    }

    /** Lista de noEmpleado de un solo departamento (27=BI, 39=TI) activo. */
    function tkNotifEquipoDepto(mysqli $conn, int $depto): array {
        $stmt = $conn->prepare(
            "SELECT noEmpleado FROM mess_rrhh.usuarios
             WHERE departamento = ? AND estatus = 1"
        );
        $stmt->bind_param('i', $depto);
        $stmt->execute();
        $res = $stmt->get_result();
        $ids = [];
        while ($r = $res->fetch_assoc()) $ids[] = (int)$r['noEmpleado'];
        $stmt->close();
        return $ids;
    }

    /**
     * Receptores para un ticket: sus asignados; si no hay, el departamento
     * dueño del ticket (BI o TI, vía ticketDepartamento), no ambos equipos.
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

        // Sin asignados: solo el departamento dueño del ticket (BI o TI), no ambos.
        if (!$destinos) $destinos = tkNotifEquipoDepto($conn, ticketDepartamento($conn, $idTicket));
        if ($excepto !== null) {
            $destinos = array_values(array_filter($destinos, fn($x) => $x !== $excepto));
        }
        return array_values(array_unique($destinos));
    }

    /**
     * Usuarios que se pueden @mencionar: cualquier empleado activo de mess_rrhh.
     * Marca es_bi=1 si pertenece al equipo BI/TI (deptos 27, 39) para decidir la pantalla
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
                    'es_bi'      => in_array((int)$r['departamento'], [27, 39]) ? 1 : 0,
                ];
            }
        }
        return $out;
    }

    /**
     * Pantalla destino para un destinatario en modo lectura (solicitante o mencionado no-gestor):
     * BI → `ver_ticket` (página completa, requiere BI); no-BI → `embed_ver` (vista slim, solo sesión).
     * Evita que un usuario fuera del depto 27 caiga en un dead-end al abrir la notificación.
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

    // ── Correo (solo alta de ticket) ─────────────────────────────────────────

    /** Correos válidos de una lista de noEmpleado (usuarios activos de mess_rrhh). */
    function tkCorreosDeEmpleados(mysqli $conn, array $noEmps): array {
        $noEmps = array_values(array_unique(array_filter(array_map('intval', $noEmps))));
        if (!$noEmps) return [];

        $ph   = implode(',', array_fill(0, count($noEmps), '?'));
        $stmt = $conn->prepare(
            "SELECT correo FROM mess_rrhh.usuarios
             WHERE noEmpleado IN ($ph) AND estatus = 1 AND correo IS NOT NULL AND correo <> ''"
        );
        if (!$stmt) return [];
        $stmt->bind_param(str_repeat('i', count($noEmps)), ...$noEmps);
        $stmt->execute();
        $res = $stmt->get_result();
        $correos = [];
        while ($r = $res->fetch_assoc()) $correos[] = trim((string)$r['correo']);
        $stmt->close();
        return $correos;
    }

    /**
     * Aviso por correo de un ticket recién creado.
     *
     * $destinos son los MISMOS noEmpleado que recibieron la notificación in-app
     * (en un alta siempre es el departamento dueño, BI o TI, porque todavía no
     * hay ingenieros asignados). Así el correo nunca se desincroniza del ruteo.
     *
     * Nunca aborta ni imprime nada: lo llama crearTicket, que responde su propio
     * JSON. Un fallo de SMTP solo se registra en el error_log. Devuelve true si
     * el correo salió.
     */
    function tkCorreoNuevoTicket(mysqli $conn, int $idTicket, array $destinos, int $solicitante): bool {
        $correos = tkCorreosDeEmpleados($conn, $destinos);
        if (!$correos) {
            error_log("Tickets correo: ticket $idTicket sin destinatarios con correo.");
            return false;
        }

        $stmt = $conn->prepare(
            "SELECT t.folio, t.titulo, t.descripcion, t.link, t.prioridad, t.fecha_creacion,
                    COALESCE(c.nombre, 'Sin categoría') AS categoria,
                    COALESCE(u.nombre, CONCAT('Empleado #', t.no_empleado_solicitante)) AS solicitante,
                    (SELECT COUNT(*) FROM tickets_adjuntos a
                      WHERE a.id_ticket = t.id AND a.id_comentario IS NULL) AS n_adjuntos
             FROM tickets t
             LEFT JOIN tickets_categorias c ON c.id = t.id_categoria
             LEFT JOIN mess_rrhh.usuarios u ON u.noEmpleado = t.no_empleado_solicitante
             WHERE t.id = ?"
        );
        if (!$stmt) return false;
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $t = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$t) return false;

        require_once __DIR__ . '/includes/correo.php';

        $esc = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

        // Colores de prioridad: los mismos semáforos que la bandeja.
        $colorPrioridad = [
            'baja'    => '#0fa083',
            'media'   => '#484cac',
            'alta'    => '#EAAA00',
            'urgente' => '#dc3545',
        ][$t['prioridad']] ?? '#6c757d';

        $fila = fn($k, $v) =>
            '<tr><td style="padding:4px 12px 4px 0;color:#6c757d;white-space:nowrap;vertical-align:top;">'
            . $k . '</td><td style="padding:4px 0;">' . $v . '</td></tr>';

        $filas  = $fila('Folio',       '<strong>' . $esc($t['folio']) . '</strong>');
        $filas .= $fila('Solicitante', $esc($t['solicitante']));
        $filas .= $fila('Categoría',   $esc($t['categoria']));
        $filas .= $fila('Prioridad',
            '<span style="display:inline-block;padding:2px 10px;border-radius:10px;font-size:13px;'
            . 'color:#ffffff;background:' . $colorPrioridad . ';">'
            . $esc(ucfirst($t['prioridad'])) . '</span>');
        $filas .= $fila('Fecha', $esc(date('d/m/Y H:i', strtotime($t['fecha_creacion']))));
        if (!empty($t['link'])) {
            $filas .= $fila('Enlace',
                '<a href="' . $esc($t['link']) . '" style="color:#050D9E;">' . $esc($t['link']) . '</a>');
        }
        if ((int)$t['n_adjuntos'] > 0) {
            $filas .= $fila('Adjuntos', (int)$t['n_adjuntos'] . ' archivo(s) en el ticket');
        }

        // La descripción se recorta: el correo es un aviso, el detalle está en el
        // sistema. nl2br para conservar los saltos de línea que escribió el usuario.
        $descripcion = (string)$t['descripcion'];
        $recortada   = mb_strlen($descripcion) > 600;
        $descripcion = nl2br($esc(mb_substr($descripcion, 0, 600))) . ($recortada ? '…' : '');

        $url = TK_URL_BASE . '/gestionar_ticket.php?id=' . $idTicket;

        $cuerpo =
            '<p style="margin:0 0 16px;">Se registró un nuevo ticket para tu equipo:</p>'
            . '<p style="margin:0 0 14px;font-size:17px;font-weight:bold;color:#050D9E;">'
            . $esc($t['titulo']) . '</p>'
            . '<table style="font-size:14px;line-height:1.6;border-collapse:collapse;">' . $filas . '</table>'
            . '<div style="margin:18px 0 0;padding:12px 16px;background:#f8f9fa;border-left:3px solid #050D9E;'
            . 'border-radius:4px;font-size:14px;">' . $descripcion . '</div>'
            . '<p style="margin:24px 0 8px;text-align:center;">'
            . '<a href="' . $url . '" style="display:inline-block;padding:11px 26px;background:#050D9E;'
            . 'color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">Ver ticket</a></p>'
            . '<p style="margin:0;text-align:center;font-size:12px;color:#6c757d;">'
            . 'Si el enlace te pide iniciar sesión, entra a MessBook y abre el sistema de Tickets.</p>';

        $res = tkEnviarCorreo(
            $correos,
            'Nuevo ticket ' . $t['folio'] . ' — ' . mb_substr($t['titulo'], 0, 60),
            'Aviso de Nuevo Ticket',
            $cuerpo
        );

        if (!$res['ok']) {
            error_log("Tickets correo: alta de {$t['folio']} (id $idTicket) no enviada — {$res['error']}");
        }
        return $res['ok'];
    }

    // ── Eventos directos ─────────────────────────────────────────────────────

    function tkNotificarNuevoTicket(mysqli $conn, int $idTicket, int $solicitante, string $folio, string $titulo): void {
        $destinos = tkNotifReceptoresBi($conn, $idTicket, $solicitante);
        $recordar = "Nuevo ticket {$folio}: " . mb_substr($titulo, 0, 80);
        foreach ($destinos as $d) {
            tkNotifInsertar($conn, $solicitante, $d, 'NuevoTicket', 'gestionar_ticket', $idTicket, $recordar);
        }
        // Aviso por correo a los MISMOS destinos que la notificación in-app, para
        // que el equipo dueño se entere sin tener que estar dentro de MessBook.
        // Solo en alta: ningún otro evento manda correo.
        tkCorreoNuevoTicket($conn, $idTicket, $destinos, $solicitante);
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

    /**
     * El solicitante canceló su propio ticket: avisa al equipo/asignados del
     * departamento dueño. Reusa la acción 'CambioEstadoTicket' (ya conocida por la
     * UI de loginMaster) y abre en la pantalla de gestión.
     */
    function tkNotificarCancelacionUsuario(mysqli $conn, int $idTicket, int $solicitante): void {
        $stmt = $conn->prepare("SELECT folio FROM tickets WHERE id = ?");
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return;

        foreach (tkNotifReceptoresBi($conn, $idTicket, $solicitante) as $d) {
            tkNotifInsertar($conn, $solicitante, $d, 'CambioEstadoTicket', 'gestionar_ticket', $idTicket,
                "El solicitante canceló el ticket {$row['folio']}");
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
        while ($t = $res->fetch_assoc()) {
            $recordar = "Sin asignar: {$t['folio']} lleva 2+ días esperando ingeniero";
            // Solo el departamento dueño del ticket (BI o TI), no ambos.
            foreach (tkNotifEquipoDepto($conn, ticketDepartamento($conn, (int)$t['id'])) as $d) {
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
