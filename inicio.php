<?php
require_once 'conn.php';
require_once 'auth.php';
$noEmpSesion = requiereSesionPage();
requiereBiPage($conn, $noEmpSesion);

$pageTitle = 'Dashboard';
include 'encabezado.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-tachometer-alt me-2 text-primary-custom"></i>Resumen</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Inicio</li>
            </ol>
        </nav>
    </div>
    <a href="nuevo_ticket.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Nuevo Ticket
    </a>
</div>

<!-- ── KPI Cards ── -->
<div class="row g-3 mb-4" id="kpiCards">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card border-left-primary h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Total Tickets</div>
                    <div class="stat-value" id="kpiTotal">—</div>
                </div>
                <i class="fas fa-ticket-alt stat-icon"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card border-left-info h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Nuevos</div>
                    <div class="stat-value" id="kpiNuevos">—</div>
                </div>
                <i class="fas fa-inbox stat-icon"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card border-left-warning h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">En Proceso</div>
                    <div class="stat-value" id="kpiEnProceso">—</div>
                </div>
                <i class="fas fa-spinner stat-icon"></i>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card stat-card border-left-success h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-label">Resueltos</div>
                    <div class="stat-value" id="kpiResueltos">—</div>
                </div>
                <i class="fas fa-check-circle stat-icon"></i>
            </div>
        </div>
    </div>
</div>

<!-- ── Gráficas ── -->
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-chart-pie me-1 text-primary-custom"></i> Tickets por Categoría
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div style="max-width:320px;width:100%;position:relative;height:260px">
                    <canvas id="graficaCategoria"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <i class="fas fa-chart-bar me-1 text-primary-custom"></i> Tickets por Mes (año actual)
            </div>
            <div class="card-body">
                <canvas id="graficaMeses" height="110"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ── Últimos Tickets ── -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-history me-1 text-primary-custom"></i> Últimos Tickets</span>
        <a href="mis_tickets.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaUltimos">
                <thead class="table-light">
                    <tr>
                        <th>Folio</th>
                        <th>Título</th>
                        <th>Categoría</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbodyUltimos">
                    <tr><td colspan="7" class="text-center py-4 text-muted">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
$(function () {
    var noEmpleado = getCookie('noEmpleadoBI');
    // Quien llega a inicio.php ya pasó requiereBiPage server-side: es BI.
    cargarEstadisticas(noEmpleado, true);
    cargarUltimosTickets(noEmpleado, true);
});

var chartCategoria = null;
var chartMeses = null;

function cargarEstadisticas(noEmpleado, esBi) {
    ajaxPost('acciones_tickets.php', {
        accion: 'obtenerEstadisticas',
        no_empleado: noEmpleado,
        es_bi: esBi ? 1 : 0
    }, function (err, res) {
        if (err || !res || !res.success) return;
        var d = res.datos;
        $('#kpiTotal').text(d.total || 0);
        $('#kpiNuevos').text(d.nuevos || 0);
        $('#kpiEnProceso').text(d.en_proceso || 0);
        $('#kpiResueltos').text(d.resueltos || 0);

        // Dona categorías — paleta MESS desde tokens
        var paletaCategorias = [
            messColor('accent'),
            messColor('info'),
            messColor('success'),
            messColor('warning'),
            messColor('danger'),
            messColor('text-muted'),
            messColor('mess-naranja'),
            messColor('accent-dark')
        ];
        if (chartCategoria) chartCategoria.destroy();
        chartCategoria = new Chart(document.getElementById('graficaCategoria'), {
            type: 'doughnut',
            data: {
                labels: d.categorias.map(function (c) { return c.nombre; }),
                datasets: [{ data: d.categorias.map(function (c) { return c.total; }), backgroundColor: paletaCategorias, borderWidth: 2, borderColor: messColor('card-bg') }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, color: messColor('text') } } }
            }
        });

        // Barras por mes
        if (chartMeses) chartMeses.destroy();
        chartMeses = new Chart(document.getElementById('graficaMeses'), {
            type: 'bar',
            data: {
                labels: d.meses.map(function (m) { return m.mes; }),
                datasets: [{
                    label: 'Tickets',
                    data: d.meses.map(function (m) { return m.total; }),
                    backgroundColor: messRgba('accent', 0.7),
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: messColor('text-muted') }, grid: { color: messColor('border') } },
                    y: { beginAtZero: true, ticks: { stepSize: 1, color: messColor('text-muted') }, grid: { color: messColor('border') } }
                }
            }
        });
    });
}

// Redibujar gráficas al cambiar de tema (recoge los nuevos colores)
document.addEventListener('mess:themechange', function () {
    cargarEstadisticas(getCookie('noEmpleadoBI'), true);
});

function cargarUltimosTickets(noEmpleado, esBi) {
    ajaxPost('acciones_tickets.php', {
        accion: 'obtenerTickets',
        no_empleado: noEmpleado,
        es_bi: esBi ? 1 : 0,
        limite: 10
    }, function (err, res) {
        var tbody = document.getElementById('tbodyUltimos');
        if (err || !res || !res.success) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error al cargar tickets.</td></tr>';
            return;
        }
        if (!res.tickets || res.tickets.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Sin tickets registrados.</td></tr>';
            return;
        }
        var html = '';
        res.tickets.forEach(function (t) {
            html += '<tr>'
                + '<td><span class="folio-badge text-primary-custom">' + escHtml(t.folio) + '</span></td>'
                + '<td>' + escHtml(t.titulo) + '</td>'
                + '<td>' + escHtml(t.categoria) + '</td>'
                + '<td>' + obtenerBadgePrioridad(t.prioridad) + '</td>'
                + '<td>' + obtenerBadgeEstado(t.estado) + '</td>'
                + '<td class="fs-7">' + formatearFecha(t.fecha_creacion) + '</td>'
                + '<td><a href="ver_ticket.php?id=' + t.id + '" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>'
                + '</tr>';
        });
        tbody.innerHTML = html;
    });
}
</script>

<?php include 'pie.php'; ?>
