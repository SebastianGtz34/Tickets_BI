<?php
require_once 'conn.php';
require_once 'auth.php';
$noEmpSesion = requiereSesionPage();
requiereBiPage($conn, $noEmpSesion);

$pageTitle = 'Mis Tickets';
include 'encabezado.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-list me-2 text-primary-custom"></i>Mis Tickets</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="inicio.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Mis Tickets</li>
            </ol>
        </nav>
    </div>
    <a href="nuevo_ticket.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Nuevo Ticket
    </a>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label mb-1 fs-7 fw-600">Estado</label>
                <select class="form-select form-select-sm" id="filtroEstado">
                    <option value="">Todos</option>
                    <option value="nuevo">Nuevo</option>
                    <option value="en_proceso">En Proceso</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="resuelto">Resuelto</option>
                    <option value="cerrado">Cerrado</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label mb-1 fs-7 fw-600">Prioridad</label>
                <select class="form-select form-select-sm" id="filtroPrioridad">
                    <option value="">Todas</option>
                    <option value="baja">Baja</option>
                    <option value="media">Media</option>
                    <option value="alta">Alta</option>
                    <option value="urgente">Urgente</option>
                </select>
            </div>
            <div class="col-12 col-md-auto">
                <button class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltros">
                    <i class="fas fa-eraser me-1"></i>Limpiar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaMisTickets" width="100%">
                <thead class="table-light">
                    <tr>
                        <th>Folio</th>
                        <th>Título</th>
                        <th>Categoría</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
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
    var noEmpleado = getCookie('noEmpleadoL');

    dt = $('#tablaMisTickets').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-MX.json' },
        ajax: {
            url: 'acciones_tickets.php',
            type: 'POST',
            data: function (d) {
                return $.extend(d, {
                    accion: 'obtenerTickets',
                    no_empleado: noEmpleado,
                    estado: $('#filtroEstado').val(),
                    prioridad: $('#filtroPrioridad').val()
                });
            },
            dataSrc: function (res) { return res.success ? res.tickets : []; }
        },
        columns: [
            { data: 'folio', render: function (v) { return '<span class="folio-badge text-primary-custom">' + escHtml(v) + '</span>'; } },
            { data: 'titulo', render: function (v) { return '<span title="' + escHtml(v) + '">' + escHtml(v.length > 50 ? v.substring(0, 50) + '…' : v) + '</span>'; } },
            { data: 'categoria', defaultContent: '—' },
            { data: 'prioridad', render: function (v) { return obtenerBadgePrioridad(v); } },
            { data: 'estado', render: function (v) { return obtenerBadgeEstado(v); } },
            { data: 'fecha_creacion', render: function (v) { return '<span class="fs-7">' + formatearFecha(v) + '</span>'; } },
            {
                data: 'id', orderable: false, className: 'text-center',
                render: function (v) {
                    return '<a href="ver_ticket.php?id=' + v + '" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i>Ver</a>';
                }
            }
        ],
        order: [[5, 'desc']],
        pageLength: 15,
        responsive: true
    });

    // Re-aplicar filtros
    $('#filtroEstado, #filtroPrioridad').on('change', function () { dt.ajax.reload(); });
    $('#btnLimpiarFiltros').on('click', function () {
        $('#filtroEstado, #filtroPrioridad').val('');
        dt.ajax.reload();
    });
});
</script>

<?php include 'pie.php'; ?>
