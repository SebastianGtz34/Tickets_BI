<?php
    include_once 'conn.php';
    if (empty($_COOKIE['noEmpleadoBI'])) {
        echo '<script>window.location.assign("../loginMaster/index.php")</script>';
    }

    // Detectar página activa
    $paginaActual = basename($_SERVER['PHP_SELF']);
    function menuActivo($pagina, $actual) {
        return $pagina === $actual ? ' active' : '';
    }

    // Equipo BI = usuarios activos del departamento 32 (cambio 2026-05-25).
    $esEquipoBi = false;
    $stmtAcc = $conn->prepare(
        "SELECT 1 FROM mess_rrhh.usuarios
         WHERE noEmpleado = ? AND departamento = 32 AND estatus = 1 LIMIT 1"
    );
    if ($stmtAcc) {
        $noEmpMenu = intval($_COOKIE['noEmpleadoBI']);
        $stmtAcc->bind_param("i", $noEmpMenu);
        $stmtAcc->execute();
        $esEquipoBi = $stmtAcc->get_result()->num_rows > 0;
        $stmtAcc->close();
    }
?>
<!-- Sidebar -->
<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

    <!-- Sidebar - Brand -->
    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="inicio.php">
        <div class="sidebar-brand-icon rotate-n-1">
            <img class="sidebar-card-illustration mb-0" src="img/MESS_07_CuboMess_2.png" width="40">
        </div>
    </a>

    <!-- Nav Item - Dashboard -->
    <li class="nav-item<?= menuActivo('inicio.php', $paginaActual) ?>">
        <a class="nav-link py-2" href="inicio.php">
            <i class="fas fa-fw fa-home"></i>
            <span>Inicio</span>
        </a>
    </li>

    <hr class="sidebar-divider">

    <li class="nav-item<?= menuActivo('nuevo_ticket.php', $paginaActual) ?>">
        <a class="nav-link py-2" href="nuevo_ticket.php">
            <i class="fas fa-fw fa-plus-circle"></i>
            <span>Nuevo Ticket</span>
        </a>
    </li>

    <li class="nav-item<?= menuActivo('mis_tickets.php', $paginaActual) ?>">
        <a class="nav-link py-2" href="mis_tickets.php">
            <i class="fas fa-fw fa-list"></i>
            <span>Mis Tickets</span>
        </a>
    </li>

    <?php if ($esEquipoBi): ?>
    <hr class="sidebar-divider">
    <div class="sidebar-heading">Equipo BI</div>

    <!-- Menú Bandeja -->
    <li class="nav-item<?= menuActivo('bandeja.php', $paginaActual) ?>">
        <a class="nav-link py-2" href="bandeja.php">
            <i class="fas fa-fw fa-inbox"></i>
            <span>Bandeja</span>
        </a>
    </li>

    <!-- Menú Reportes -->
    <li class="nav-item<?= menuActivo('reportes.php', $paginaActual) ?>">
        <a class="nav-link py-2" href="reportes.php">
            <i class="fas fa-fw fa-chart-bar"></i>
            <span>Reportes</span>
        </a>
    </li>

    <!-- Menú Catálogos -->
    <li class="nav-item<?= menuActivo('catalogos.php', $paginaActual) ?>">
        <a class="nav-link py-2" href="catalogos.php">
            <i class="fas fa-fw fa-tags"></i>
            <span>Catálogos</span>
        </a>
    </li>
    <?php endif; ?>

    <hr class="sidebar-divider">

    <!-- SALIR -->
    <li class="nav-item">
        <a class="nav-link py-2" href="logout.php">
            <i class="fas fa-fw fa-sign-out-alt"></i>
            <span>Salir</span>
        </a>
    </li>

    <!-- Divider -->
    <hr class="sidebar-divider d-none d-md-block">

    <!-- Sidebar Toggler -->
    <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" id="sidebarToggle" type="button"></button>
    </div>
</ul>
<!-- End of Sidebar -->
