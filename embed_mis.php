<?php
// Vista slim para embeber en loginMaster vía iframe.
// Solo requiere sesión válida (cookie noEmpleadoBI). No requiere rol BI.
require_once 'conn.php';
require_once 'auth.php';
$noEmpSesion = requiereSesionPage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Tickets</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="css/estilos.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { background: var(--bg); padding: 1rem; }
    </style>
</head>
<body>

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
                        <th class="text-center">Ver</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
function getCookie(name) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.*+?^${}()|[\]\\])/g, '\\$1') + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : null;
}

function escHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function badgeEstado(estado) {
    var map = {
        nuevo:['badge-nuevo','Nuevo'], en_proceso:['badge-en_proceso','En Proceso'],
        pendiente:['badge-pendiente','Pendiente'], resuelto:['badge-resuelto','Resuelto'],
        cerrado:['badge-cerrado','Cerrado']
    };
    var d = map[estado] || ['bg-secondary', estado];
    return '<span class="badge ' + d[0] + '">' + d[1] + '</span>';
}

function badgePrioridad(p) {
    var map = { baja:['badge-baja','Baja'], media:['badge-media','Media'], alta:['badge-alta','Alta'], urgente:['badge-urgente','Urgente'] };
    var d = map[p] || ['bg-secondary', p];
    return '<span class="badge ' + d[0] + '">' + d[1] + '</span>';
}

function formatearFecha(f) {
    if (!f) return '—';
    var d = new Date(f.replace(' ','T'));
    if (isNaN(d)) return f;
    var p = function (n) { return String(n).padStart(2,'0'); };
    return p(d.getDate())+'/'+p(d.getMonth()+1)+'/'+d.getFullYear()+' '+p(d.getHours())+':'+p(d.getMinutes());
}

var dt;
$(function () {
    dt = $('#tablaMisTickets').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-MX.json' },
        ajax: {
            url: 'acciones_tickets.php',
            type: 'POST',
            data: function (d) {
                return $.extend(d, {
                    accion: 'obtenerTickets',
                    no_empleado: getCookie('noEmpleadoBI'),
                    estado: $('#filtroEstado').val(),
                    prioridad: $('#filtroPrioridad').val()
                });
            },
            dataSrc: function (res) { return res.success ? res.tickets : []; }
        },
        columns: [
            { data: 'folio', render: function (v) { return '<span class="folio-badge text-primary-custom">' + escHtml(v) + '</span>'; } },
            { data: 'titulo', render: function (v) { return '<span title="' + escHtml(v) + '">' + escHtml(v.length > 50 ? v.substring(0,50)+'…' : v) + '</span>'; } },
            { data: 'categoria', defaultContent: '—' },
            { data: 'prioridad', render: function (v) { return badgePrioridad(v); } },
            { data: 'estado',    render: function (v) { return badgeEstado(v); } },
            { data: 'fecha_creacion', render: function (v) { return '<span class="fs-7">' + formatearFecha(v) + '</span>'; } },
            {
                data: 'id', orderable: false, className: 'text-center',
                render: function (v) {
                    return '<a href="embed_ver.php?id=' + v + '" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>';
                }
            }
        ],
        order: [[5, 'desc']],
        pageLength: 10,
        responsive: true
    });

    $('#filtroEstado, #filtroPrioridad').on('change', function () { dt.ajax.reload(); });
    $('#btnLimpiarFiltros').on('click', function () {
        $('#filtroEstado, #filtroPrioridad').val('');
        dt.ajax.reload();
    });
});
</script>
</body>
</html>
