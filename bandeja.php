<?php
require_once 'conn.php';
require_once 'auth.php';
$noEmpSesion = requiereSesionPage();
requiereBiPage($conn, $noEmpSesion);
$deptoSesion = obtenerNombreDepto($conn, $noEmpSesion) ?: 'bi';

$pageTitle = 'Bandeja de Tickets';
include 'encabezado.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-inbox me-2 text-primary-custom"></i>Bandeja de Tickets</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="inicio.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Bandeja</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label mb-1 fs-7 fw-600">Estado</label>
                <select class="form-select form-select-sm" id="filtroEstado">
                    <option value="">Todos</option>
                    <option value="nuevo">Nuevo</option>
                    <option value="en_proceso">En Proceso</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="resuelto">Resuelto</option>
                    <option value="cerrado">Cerrado</option>
                    <option value="cancelado">Cancelado</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label mb-1 fs-7 fw-600">Prioridad</label>
                <select class="form-select form-select-sm" id="filtroPrioridad">
                    <option value="">Todas</option>
                    <option value="baja">Baja</option>
                    <option value="media">Media</option>
                    <option value="alta">Alta</option>
                    <option value="urgente">Urgente</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1 fs-7 fw-600">Fecha desde</label>
                <input type="date" class="form-control form-control-sm" id="filtroFechaDesde">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1 fs-7 fw-600">Fecha hasta</label>
                <input type="date" class="form-control form-control-sm" id="filtroFechaHasta">
            </div>
            <div class="col-auto d-flex gap-2">
                <div class="form-check form-check-inline mb-0 align-self-end">
                    <input class="form-check-input" type="checkbox" id="filtroAsignadoMi">
                    <label class="form-check-label fs-7" for="filtroAsignadoMi">Asignado a mí</label>
                </div>
                <button class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltros">
                    <i class="fas fa-eraser"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaBandeja" width="100%">
                <thead class="table-light">
                    <tr>
                        <th>Folio</th>
                        <th>Título</th>
                        <th>Categoría</th>
                        <th>Solicitante</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Asignado a</th>
                        <th>Fecha</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
var dt;

$(function () {
    var noEmpleado = getCookie('noEmpleadoBI');

    dt = $('#tablaBandeja').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-MX.json' },
        ajax: {
            url: 'acciones_tickets.php',
            type: 'POST',
            data: function (d) {
                return $.extend(d, {
                    accion: 'obtenerTickets',
                    filtro_categoria_depto: 1,
                    no_empleado: noEmpleado,
                    estado: $('#filtroEstado').val(),
                    prioridad: $('#filtroPrioridad').val(),
                    fecha_desde: $('#filtroFechaDesde').val(),
                    fecha_hasta: $('#filtroFechaHasta').val(),
                    solo_asignado: $('#filtroAsignadoMi').is(':checked') ? 1 : 0
                });
            },
            dataSrc: function (res) { return res.success ? res.tickets : []; }
        },
        columns: [
            { data: 'folio', render: function (v) { return '<span class="folio-badge text-primary-custom">' + escHtml(v) + '</span>'; } },
            { data: 'titulo', render: function (v) { return escHtml(v.length > 45 ? v.substring(0, 45) + '…' : v); } },
            { data: 'categoria', defaultContent: '—' },
            { data: 'nombre_solicitante', defaultContent: '—' },
            { data: 'prioridad', render: function (v) { return obtenerBadgePrioridad(v); } },
            { data: 'estado', render: function (v) { return obtenerBadgeEstado(v); } },
            { data: 'asignados_nombres', render: function (v) { return v ? escHtml(v) : '<span class="text-muted fs-7">Sin asignar</span>'; } },
            { data: 'fecha_creacion', render: function (v) { return '<span class="fs-7">' + formatearFecha(v) + '</span>'; } },
            {
                data: 'id', orderable: false, className: 'text-center',
                render: function (v) {
                    return '<a href="gestionar_ticket.php?id=' + v + '" class="btn btn-sm btn-primary">'
                        + '<i class="fas fa-tasks me-1"></i>Gestionar</a>';
                }
            }
        ],
        order: [[7, 'desc']],
        pageLength: 20,
        responsive: true
    });

    $('#filtroEstado, #filtroPrioridad, #filtroFechaDesde, #filtroFechaHasta, #filtroAsignadoMi').on('change', function () {
        dt.ajax.reload();
    });

    $('#btnLimpiarFiltros').on('click', function () {
        $('#filtroEstado, #filtroPrioridad, #filtroFechaDesde, #filtroFechaHasta').val('');
        $('#filtroAsignadoMi').prop('checked', false);
        dt.ajax.reload();
    });
});
</script>

<?php include 'pie.php'; ?>
