<?php
/**
 * Helpers de autenticación y autorización para Tickets BI/TI.
 *
 * Patrón: cookie `noEmpleadoBI` (set por loginMaster) + tabla cross-DB
 * `mess_rrhh.usuarios.departamento IN (32, 38)` (equipos BI y TI).
 *
 * Reglas:
 * - Verificación SIEMPRE en backend antes de ejecutar acción protegida.
 * - Nunca confiar en parámetros del cliente para decidir privilegios.
 */

// Constantes de departamentos y colores
define('DEPTS_ALLOWED', [32, 38]); // BI=32, TI=38
define('DEPT_BI', 32);
define('DEPT_TI', 38);
define('COLORES_TIPO', [
    'sistema' => '#050D9E',  // Azul oscuro (accent MESS)
    'ti'      => '#4a90e2',  // Azul claro
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
     * Determina si el empleado tiene acceso a Tickets (BI o TI).
     * Acceso = usuarios activos de departamentos permitidos (32=BI, 38=TI).
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
     * Determina si el empleado pertenece al equipo BI (depto 32).
     * Mantiene compatibilidad; usa tieneAccesoTickets internamente.
     * DEPRECATED: usar tieneAccesoTickets() para nuevas verificaciones.
     */
    function tieneAccesoBi(mysqli $conn, int $noEmpleado): bool {
        return tieneAccesoTickets($conn, $noEmpleado);
    }

    /** Devuelve 'bi', 'ti' o '' según el departamento del empleado. */
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
        $map = [DEPT_BI => 'bi', DEPT_TI => 'ti'];
        return $cache[$noEmpleado] = $map[($row['departamento'] ?? 0)] ?? '';
    }

    /** Para PÁGINAS: redirige a loginMaster si no tiene rol BI. Tickets BI es admin-only. */
    function requiereBiPage(mysqli $conn, int $noEmpleado): void {
        if (!tieneAccesoBi($conn, $noEmpleado)) {
            header('Location: ../loginMaster/inicio.php');
            exit;
        }
    }

    /** Para ENDPOINTS JSON: responde 403 si no tiene rol BI. */
    function requiereBiJson(mysqli $conn, int $noEmpleado): void {
        if (!tieneAccesoBi($conn, $noEmpleado)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tienes permiso para esta acción.']);
            exit;
        }
    }
}
