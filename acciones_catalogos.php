<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'conn.php';
require_once 'auth.php';

$noEmpSesion = requiereSesionJson();

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

function responder(bool $success, string $message = '', array $extra = []): void {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

// Tipos válidos de categoría; cualquier otro valor se guarda como 'otro'.
// 'ti' y 'rrhh' mandan el ticket a ese departamento (ticketDepartamento en auth.php).
const TIPOS_CATEGORIA = ['sistema', 'ti', 'rrhh', 'otro'];

// Acciones que requieren ser staff (escritura de catálogo)
$accionesBi = ['crearCategoria', 'actualizarCategoria', 'toggleCategoria'];
if (in_array($accion, $accionesBi, true)) {
    requiereBiJson($conn, $noEmpSesion);
}

switch ($accion) {

    // ── OBTENER CATEGORÍAS ─────────────────────────────────────────────────────
    case 'obtenerCategorias': {
        $soloActivas = (int)($_POST['solo_activas'] ?? $_GET['solo_activas'] ?? 0);
        $where = $soloActivas ? 'WHERE activo = 1' : '';
        // 'Otro / General' siempre al final; el resto mantiene su orden de creación.
        $res = $conn->query("SELECT * FROM tickets_categorias $where ORDER BY (nombre = 'Otro / General') ASC, id ASC");
        $cats = [];
        while ($r = $res->fetch_assoc()) $cats[] = $r;
        responder(true, '', ['categorias' => $cats]);
    }

    // ── CREAR CATEGORÍA ───────────────────────────────────────────────────────
    case 'crearCategoria': {
        $nombre = trim($_POST['nombre'] ?? '');
        $grupo  = trim($_POST['grupo'] ?? '') ?: null; // vacío = sin grupo (se agrupa por tipo)
        $tipo   = in_array($_POST['tipo'] ?? '', TIPOS_CATEGORIA, true) ? $_POST['tipo'] : 'otro';

        if (!$nombre) responder(false, 'El nombre es obligatorio.');

        // Verificar duplicado
        $chk = $conn->prepare("SELECT id FROM tickets_categorias WHERE nombre = ?");
        $chk->bind_param('s', $nombre);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            responder(false, 'Ya existe una categoría con ese nombre.');
        }
        $chk->close();

        $stmt = $conn->prepare(
            "INSERT INTO tickets_categorias (nombre, grupo, tipo, activo) VALUES (?, ?, ?, 1)"
        );
        $stmt->bind_param('sss', $nombre, $grupo, $tipo);
        $ok = $stmt->execute();
        $id = (int)$conn->insert_id;
        $stmt->close();

        responder($ok, $ok ? '' : 'Error al crear la categoría.', $ok ? ['id' => $id] : []);
    }

    // ── ACTUALIZAR CATEGORÍA ───────────────────────────────────────────────────
    case 'actualizarCategoria': {
        $id     = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $grupo  = trim($_POST['grupo'] ?? '') ?: null;
        $tipo   = in_array($_POST['tipo'] ?? '', TIPOS_CATEGORIA, true) ? $_POST['tipo'] : 'otro';

        if (!$id || !$nombre) responder(false, 'Datos inválidos.');

        // Verificar duplicado excluyendo la misma
        $chk = $conn->prepare("SELECT id FROM tickets_categorias WHERE nombre = ? AND id != ?");
        $chk->bind_param('si', $nombre, $id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            responder(false, 'Ya existe otra categoría con ese nombre.');
        }
        $chk->close();

        $stmt = $conn->prepare(
            "UPDATE tickets_categorias SET nombre = ?, grupo = ?, tipo = ? WHERE id = ?"
        );
        $stmt->bind_param('sssi', $nombre, $grupo, $tipo, $id);
        $ok = $stmt->execute();
        $stmt->close();

        responder($ok, $ok ? '' : 'Error al actualizar la categoría.');
    }

    // ── TOGGLE ACTIVO ──────────────────────────────────────────────────────────
    case 'toggleCategoria': {
        $id     = (int)($_POST['id']     ?? 0);
        $activo = (int)($_POST['activo'] ?? 0);

        if (!$id) responder(false, 'ID inválido.');

        $stmt = $conn->prepare("UPDATE tickets_categorias SET activo = ? WHERE id = ?");
        $stmt->bind_param('ii', $activo, $id);
        $ok = $stmt->execute();
        $stmt->close();

        responder($ok, $ok ? '' : 'Error al actualizar.');
    }

    default:
        responder(false, 'Acción no reconocida.');
}
