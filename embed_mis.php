<?php
// Vista slim para embeber en loginMaster vía iframe.
// Solo requiere sesión válida (cookie noEmpleadoBI). No requiere rol BI.
//
// Comportamiento según el rol del que abre la vista:
//   - BI/TI (gestor): carga la Bandeja de su departamento (todos los tickets
//     del tipo correspondiente), con columna "Asignado a", filtros de fecha y
//     "asignado a mí", y acción "Gestionar" → gestionar_ticket.php.
//   - Fuera de BI/TI: solo sus propios tickets, acción "Ver" → embed_ver.php.
require_once 'conn.php';
require_once 'auth.php';
$noEmpSesion = requiereSesionPage();
$deptoSesion = obtenerNombreDepto($conn, $noEmpSesion) ?: 'bi';
$esGestor    = tieneAccesoBi($conn, $noEmpSesion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $esGestor ? 'Bandeja de Tickets' : 'Mis Tickets' ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <!-- Extension Responsive: la tabla ya pasaba responsive:true pero sin
         cargarla la opcion quedaba inerte y las 8-9 columnas se cortaban en
         movil. Con esto colapsa las que no caben en una fila desplegable. -->
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
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
            <div class="col-6 col-md-<?= $esGestor ? '2' : '3' ?>">
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
            <div class="col-6 col-md-<?= $esGestor ? '2' : '3' ?>">
                <label class="form-label mb-1 fs-7 fw-600">Prioridad</label>
                <select class="form-select form-select-sm" id="filtroPrioridad">
                    <option value="">Todas</option>
                    <option value="baja">Baja</option>
                    <option value="media">Media</option>
                    <option value="alta">Alta</option>
                    <option value="urgente">Urgente</option>
                </select>
            </div>
            <?php if ($esGestor): ?>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1 fs-7 fw-600">Fecha desde</label>
                <input type="date" class="form-control form-control-sm" id="filtroFechaDesde">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1 fs-7 fw-600">Fecha hasta</label>
                <input type="date" class="form-control form-control-sm" id="filtroFechaHasta">
            </div>
            <div class="col-auto d-flex gap-2 align-items-end">
                <div class="form-check form-check-inline mb-0 align-self-end">
                    <input class="form-check-input" type="checkbox" id="filtroAsignadoMi">
                    <label class="form-check-label fs-7" for="filtroAsignadoMi">Asignado a mí</label>
                </div>
                <button class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltros">
                    <i class="fas fa-eraser"></i>
                </button>
            </div>
            <?php else: ?>
            <div class="col-12 col-md-auto">
                <button class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltros">
                    <i class="fas fa-eraser me-1"></i>Limpiar
                </button>
            </div>
            <?php endif; ?>
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
                        <th>Solicitante</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <?php if ($esGestor): ?><th>Asignado a</th><?php endif; ?>
                        <th>Fecha</th>
                        <th class="text-center"><?= $esGestor ? 'Acciones' : 'Ver' ?></th>
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
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="js/funciones.js"></script>
<script>
function getCookie(name) {
    const cookies = new URLSearchParams(document.cookie.replace(/; /g, '&'));
    return cookies.get(name) || undefined;
}

function escHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function badgeEstado(estado) {
    var map = {
        nuevo:['badge-nuevo','Nuevo'], en_proceso:['badge-en_proceso','En Proceso'],
        pendiente:['badge-pendiente','Pendiente'], resuelto:['badge-resuelto','Resuelto'],
        cerrado:['badge-cerrado','Cerrado'], cancelado:['badge-cancelado','Cancelado']
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

// Rol del que abre la vista (derivado en el servidor). Decide dataset, columnas y acción.
var ES_GESTOR = <?= $esGestor ? 'true' : 'false' ?>;

var dt;
$(function () {
    // Columnas comunes
    var columnas = [
        { data: 'folio', render: function (v) { return '<span class="folio-badge text-primary-custom">' + escHtml(v) + '</span>'; } },
        { data: 'titulo', render: function (v) { return '<span title="' + escHtml(v) + '">' + escHtml(v.length > 50 ? v.substring(0,50)+'…' : v) + '</span>'; } },
        { data: 'categoria', defaultContent: '—' },
        { data: 'nombre_solicitante', defaultContent: '—' },
        { data: 'prioridad', render: function (v) { return badgePrioridad(v); } },
        { data: 'estado',    render: function (v) { return badgeEstado(v); } }
    ];

    // Columna "Asignado a" solo para gestores
    if (ES_GESTOR) {
        columnas.push({ data: 'asignados_nombres', render: function (v) { return v ? escHtml(v) : '<span class="text-muted fs-7">Sin asignar</span>'; } });
    }

    columnas.push({ data: 'fecha_creacion', render: function (v) { return '<span class="fs-7">' + formatearFecha(v) + '</span>'; } });

    // Acción: gestor → Gestionar (gestión completa); resto → Ver (slim)
    columnas.push({
        data: 'id', orderable: false, className: 'text-center',
        render: function (v) {
            return ES_GESTOR
                ? '<a href="gestionar_ticket.php?id=' + v + '&embed=1" class="btn btn-sm btn-primary"><i class="fas fa-tasks me-1"></i>Gestionar</a>'
                : '<a href="embed_ver.php?id=' + v + '" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>';
        }
    });

    // Índice de la columna "Fecha" (penúltima): 7 con "Asignado a", 6 sin ella.
    var idxFecha = ES_GESTOR ? 7 : 6;

    dt = $('#tablaMisTickets').DataTable({
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-MX.json' },
        ajax: {
            url: 'acciones_tickets.php',
            type: 'POST',
            data: function (d) {
                $.extend(d, {
                    accion: 'obtenerTickets',
                    no_empleado: getCookie('noEmpleadoBI'),
                    estado: $('#filtroEstado').val(),
                    prioridad: $('#filtroPrioridad').val()
                });
                if (ES_GESTOR) {
                    // Bandeja del departamento (el backend resuelve el tipo por sesión)
                    d.filtro_categoria_depto = 1;
                    d.fecha_desde   = $('#filtroFechaDesde').val();
                    d.fecha_hasta   = $('#filtroFechaHasta').val();
                    d.solo_asignado = $('#filtroAsignadoMi').is(':checked') ? 1 : 0;
                } else {
                    // Solo tickets propios
                    d.departamento = '<?= $deptoSesion ?>';
                }
                return d;
            },
            dataSrc: function (res) { return res.success ? res.tickets : []; }
        },
        columns: columnas,
        order: [[idxFecha, 'desc']],
        pageLength: ES_GESTOR ? 20 : 10,
        responsive: true
    });

    // Filtros: estado y prioridad siempre; fecha y "asignado a mí" solo para gestores.
    $('#filtroEstado, #filtroPrioridad, #filtroFechaDesde, #filtroFechaHasta, #filtroAsignadoMi').on('change', function () { dt.ajax.reload(); });
    $('#btnLimpiarFiltros').on('click', function () {
        $('#filtroEstado, #filtroPrioridad, #filtroFechaDesde, #filtroFechaHasta').val('');
        $('#filtroAsignadoMi').prop('checked', false);
        dt.ajax.reload();
    });
});
</script>
</body>
</html>
