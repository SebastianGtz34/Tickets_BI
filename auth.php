<?php
/**
 * Helpers de autenticación y autorización para Tickets BI.
 *
 * Patrón: cookie `noEmpleadoBI` (set por loginMaster) + tabla cross-DB
 * `mess_rrhh.usuarios.departamento = 32` (equipo BI).
 *
 * Reglas:
 * - Verificación SIEMPRE en backend antes de ejecutar acción protegida.
 * - Nunca confiar en parámetros del cliente para decidir privilegios.
 */

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
     * Determina si el empleado pertenece al equipo BI.
     * Equipo BI = usuarios activos del departamento 32.
     * Cachea el resultado por request en una estática.
     */
    function tieneAccesoBi(mysqli $conn, int $noEmpleado): bool {
        static $cache = [];
        if (isset($cache[$noEmpleado])) return $cache[$noEmpleado];

        $stmt = $conn->prepare(
            "SELECT 1 FROM mess_rrhh.usuarios
             WHERE noEmpleado = ? AND departamento = 32 AND estatus = 1
             LIMIT 1"
        );
        if (!$stmt) return $cache[$noEmpleado] = false;
        $stmt->bind_param('i', $noEmpleado);
        $stmt->execute();
        $tiene = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $cache[$noEmpleado] = $tiene;
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
