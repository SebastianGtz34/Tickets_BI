<?php
/**
 * Helpers de autenticación y autorización para Tickets Messbook.
 *
 * Patrón: cookie `noEmpleadoBI` (set por loginMaster) + tabla cross-DB
 * `mess_rrhh.usuarios.departamento IN DEPTS_ALLOWED` (equipos que atienden tickets).
 *
 * Reglas:
 * - Verificación SIEMPRE en backend antes de ejecutar acción protegida.
 * - Nunca confiar en parámetros del cliente para decidir privilegios.
 */

// Constantes de departamentos y colores
define('DEPTS_ALLOWED', [27, 39, 47]); // BI=27, TI=39, RRHH=47
define('DEPT_BI', 27);
define('DEPT_TI', 39);
define('DEPT_RRHH', 47);
/** Clave corta de cada departamento: es también el `tipo` de categoría de TI y RRHH. */
define('DEPT_CLAVES', [DEPT_BI => 'bi', DEPT_TI => 'ti', DEPT_RRHH => 'rrhh']);
define('COLORES_TIPO', [
    'sistema' => '#050D9E',  // Azul oscuro (accent MESS)
    'ti'      => '#4a90e2',  // Azul claro
    'rrhh'    => '#db2777',  // Magenta
    'otro'    => '#9c27b0'   // Púrpura
]);

if (!function_exists('ticketsAuthNoEmpleado')) {

    /** Devuelve el noEmpleado de la sesión (cookie noEmpleadoBI) o null. */
    function ticketsAuthNoEmpleado(): ?int {
        $v = $_COOKIE['noEmpleadoBI'] ?? null;
        if ($v === null || $v === '') return null;
        $i = (int)$v;
        return $i > 0 ? $i : null;
    }

    /** Verifica que haya sesión activa. Para PÁGINAS — redirige a loginMaster. */
    function requiereSesionPage(): int {
        $no = ticketsAuthNoEmpleado();
        if (!$no) {
            header('Location: ../loginMaster/index.php');
            exit;
        }
        return $no;
    }

    /** Verifica que haya sesión activa. Para ENDPOINTS JSON — responde 401. */
    function requiereSesionJson(): int {
        $no = ticketsAuthNoEmpleado();
        if (!$no) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sesión no válida.']);
            exit;
        }
        return $no;
    }

    /**
     * Determina si el empleado es staff de Tickets (atiende tickets de su depto).
     * Acceso = usuarios activos de departamentos permitidos (27=BI, 39=TI, 47=RRHH).
     * Cachea el resultado por request en una estática.
     */
    function tieneAccesoTickets(mysqli $conn, int $noEmpleado): bool {
        static $cache = [];
        if (isset($cache[$noEmpleado])) return $cache[$noEmpleado];

        $placeholders = implode(',', DEPTS_ALLOWED);
        $stmt = $conn->prepare(
            "SELECT 1 FROM mess_rrhh.usuarios
             WHERE noEmpleado = ? AND departamento IN ($placeholders) AND estatus = 1
             LIMIT 1"
        );
        if (!$stmt) return $cache[$noEmpleado] = false;
        $stmt->bind_param('i', $noEmpleado);
        $stmt->execute();
        $tiene = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $cache[$noEmpleado] = $tiene;
    }

    /**
     * Alias histórico de tieneAccesoTickets (staff de cualquier depto permitido).
     * DEPRECATED: usar tieneAccesoTickets() para nuevas verificaciones.
     */
    function tieneAccesoBi(mysqli $conn, int $noEmpleado): bool {
        return tieneAccesoTickets($conn, $noEmpleado);
    }

    /** Devuelve 'bi', 'ti', 'rrhh' o '' según el departamento del empleado. */
    function obtenerNombreDepto(mysqli $conn, int $noEmpleado): string {
        static $cache = [];
        if (isset($cache[$noEmpleado])) return $cache[$noEmpleado];
        $stmt = $conn->prepare(
            "SELECT departamento FROM mess_rrhh.usuarios WHERE noEmpleado = ? AND estatus = 1 LIMIT 1"
        );
        if (!$stmt) return $cache[$noEmpleado] = '';
        $stmt->bind_param('i', $noEmpleado);
        if (!$stmt->execute()) { $stmt->close(); return $cache[$noEmpleado] = ''; }
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $cache[$noEmpleado] = DEPT_CLAVES[($row['departamento'] ?? 0)] ?? '';
    }

    /** Para PÁGINAS: redirige a loginMaster si no es staff. El panel completo es solo para staff. */
    function requiereBiPage(mysqli $conn, int $noEmpleado): void {
        if (!tieneAccesoBi($conn, $noEmpleado)) {
            header('Location: ../loginMaster/inicio.php');
            exit;
        }
    }

    /** Para ENDPOINTS JSON: responde 403 si no es staff. */
    function requiereBiJson(mysqli $conn, int $noEmpleado): void {
        if (!tieneAccesoBi($conn, $noEmpleado)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para esta acción.']);
            exit;
        }
    }

    /**
     * Departamento DUEÑO de un ticket, derivado del tipo de su categoría:
     *   categoría tipo 'ti'                   → DEPT_TI (39)
     *   categoría tipo 'rrhh'                 → DEPT_RRHH (47)
     *   tipo 'sistema'/'otro' o sin categoría → DEPT_BI (27)
     * Único criterio de pertenencia ticket→departamento; lo reusan la bandeja,
     * las notificaciones y el gating de gestión. Espejo JS: deptoDeTipo().
     */
    function ticketDepartamento(mysqli $conn, int $idTicket): int {
        $stmt = $conn->prepare(
            "SELECT c.tipo
             FROM tickets t
             LEFT JOIN tickets_categorias c ON c.id = t.id_categoria
             WHERE t.id = ?"
        );
        $stmt->bind_param('i', $idTicket);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $depto = array_search($row['tipo'] ?? '', DEPT_CLAVES, true);
        return $depto !== false ? $depto : DEPT_BI;
    }

    /**
     * ¿Puede $noEmpleado GESTIONAR este ticket? Sí cuando pertenece al
     * departamento dueño del ticket, o cuando está explícitamente asignado a él
     * (asignación o @mención cross-departamento conservan acceso). Pensado para
     * staff; a un no-staff lo bloquea antes requiereBi*.
     */
    function puedeGestionarTicket(mysqli $conn, int $noEmpleado, int $idTicket): bool {
        if (DEPT_CLAVES[ticketDepartamento($conn, $idTicket)] === obtenerNombreDepto($conn, $noEmpleado)) {
            return true;
        }
        // Asignado explícitamente (p. ej. ingeniero de otro depto): conserva acceso.
        $emp  = (string)$noEmpleado;
        $stmt = $conn->prepare(
            "SELECT 1 FROM tickets_asignados WHERE id_ticket = ? AND no_empleado = ? LIMIT 1"
        );
        $stmt->bind_param('is', $idTicket, $emp);
        $stmt->execute();
        $ok = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $ok;
    }
}
