<?php
$noEmpleado = $_COOKIE['noEmpleadoBI'] ?? null;
if (!$noEmpleado) {
    header('Location: ../loginMaster/index.php');
    exit;
}
// Modo embebido (iframe en loginMaster): sin sidebar ni topbar, contenido a ancho completo.
$embed = !empty($_GET['embed']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Sistema de Tickets BI') ?></title>

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Tribute.js (@menciones) — local -->
    <link rel="stylesheet" href="css/tribute.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/estilos.css">

    <!-- jQuery 3.7.1 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Custom JS - Funciones globales (carga temprana) -->
    <script src="js/funciones.js"></script>
</head>
<body>
<script>
// Aplicar tema MESS antes de renderizar contenido visible para evitar flash
(function () {
    try {
        if (localStorage.getItem('mess-theme') === 'dark') {
            document.body.classList.add('theme-dark');
        }
    } catch (e) {}
})();
</script>

<div id="wrapper">

<?php if (!$embed) include 'menu.php'; ?>

<!-- ══ PAGE CONTENT ══ -->
<div id="page-content-wrapper">

    <?php if (!$embed): ?>
    <!-- ── Topbar ── -->
    <nav id="topbar">
        <div class="d-flex align-items-center gap-2">
            <button id="sidebarToggleTop" class="btn btn-link text-secondary p-0" type="button" aria-label="Mostrar/ocultar menú" title="Mostrar/ocultar menú">
                <i class="fas fa-bars fa-lg"></i>
            </button>
            <span class="fw-600 d-none d-md-inline">
                <?= htmlspecialchars($pageTitle ?? 'Sistema de Tickets BI') ?>
            </span>
        </div>

        <div class="d-flex align-items-center">
            <div class="text-end me-3 d-none d-md-block">
                <div class="fw-600 fs-7" id="nombreUsuario">Cargando...</div>
                <div class="text-muted fs-8"># <?= htmlspecialchars((string)$noEmpleado) ?></div>
            </div>
            <div class="topbar-divider d-none d-md-block"></div>
            <button id="themeToggle" type="button" class="theme-toggle-btn me-2" title="Cambiar tema">
                <i class="fas fa-moon"></i>
            </button>
            <a href="logout.php" class="btn btn-sm btn-outline-secondary" title="Volver al panel">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </nav>
    <!-- ── /Topbar ── -->
    <?php endif; ?>

    <!-- CONTENT START -->
    <div class="container-fluid content-area">

<script>
// Cargar nombre del empleado
$(function () {
    var noEmp  = getCookie('noEmpleadoBI');
    var nombre = getCookie('nombredelusuarioBI') || ('Empleado #' + noEmp);
    $('#nombreUsuario').text(nombre);
});
</script>
