<?php
/**
 * Login temporal para desarrollo.
 * ELIMINAR antes de pasar a producción.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $noEmpleado = trim($_POST['no_empleado'] ?? '');
    $nombre     = trim($_POST['nombre']      ?? 'Usuario Prueba');

    if ($noEmpleado) {
        setcookie('noEmpleado',      $noEmpleado, time() + 86400, '/');
        setcookie('nombreEmpleado',  $nombre,     time() + 86400, '/');
        header('Location: inicio.php');
        exit;
    }
    $error = 'Ingresa un número de empleado.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login de Prueba — Tickets BI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        body { background: var(--mess-azul); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 380px; }
        .badge-dev { background: var(--mess-naranja); color: #fff; font-size: .7rem; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="card shadow-lg border-0">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h4 class="fw-bold text-primary-custom">
                    <i class="fas fa-ticket-alt me-1"></i> Tickets BI
                </h4>
                <span class="badge badge-dev">
                    <i class="fas fa-flask me-1"></i>Login de desarrollo — no usar en producción
                </span>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 fs-7"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">No. Empleado</label>
                    <input type="text" name="no_empleado" class="form-control" placeholder="Ej. 10001" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Nombre (opcional)</label>
                    <input type="text" name="nombre" class="form-control" placeholder="Tu nombre completo">
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-sign-in-alt me-1"></i> Entrar al sistema
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
