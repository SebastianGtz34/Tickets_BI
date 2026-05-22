<?php
require_once 'conn.php';
require_once 'auth.php';
$noEmpSesion = requiereSesionPage();
requiereBiPage($conn, $noEmpSesion);

$pageTitle = 'Reportes';
include 'encabezado.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-chart-bar me-2 text-primary-custom"></i>Reportes</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="inicio.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Reportes</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label mb-1 fs-7 fw-600">Fecha desde</label>
                <input type="date" class="form-control form-control-sm" id="filtroDesde">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1 fs-7 fw-600">Fecha hasta</label>
                <input type="date" class="form-control form-control-sm" id="filtroHasta">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label mb-1 fs-7 fw-600">Categoría</label>
                <select class="form-select form-select-sm" id="filtroCategoria">
                    <option value="">Todas</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary btn-sm" id="btnGenerar">
                    <i class="fas fa-sync-alt me-1"></i>Generar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card border-left-primary text-center">
            <div class="card-body">
                <div class="stat-label">Total</div>
                <div class="stat-value" id="rTotal">—</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card border-left-success text-center">
            <div class="card-body">
                <div class="stat-label">Resueltos</div>
                <div class="stat-value" id="rResueltos">—</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card border-left-danger text-center">
            <div class="card-body">
                <div class="stat-label">Abiertos</div>
                <div class="stat-value" id="rAbiertos">—</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card border-left-info text-center">
            <div class="card-body">
                <div class="stat-label">T. Promedio Cierre</div>
                <div class="stat-value fs-5" id="rPromedio">—</div>
            </div>
        </div>
    </div>
</div>

<!-- Gráficas fila 1 -->
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-calendar-alt me-1"></i>Tickets por Mes</div>
            <div class="card-body"><canvas id="gMeses" height="200"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-tags me-1"></i>Tickets por Categoría</div>
            <div class="card-body"><canvas id="gCategorias" height="200"></canvas></div>
        </div>
    </div>
</div>

<!-- Gráficas fila 2 -->
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-users me-1"></i>Tickets por Agente</div>
            <div class="card-body"><canvas id="gAgentes" height="200"></canvas></div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="fas fa-exclamation-triangle me-1"></i>Tickets por Prioridad</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div style="max-width:280px;width:100%;height:220px;position:relative">
                    <canvas id="gPrioridades"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var charts = {};

$(function () {
    // Valores por defecto: mes actual
    var hoy = new Date();
    var primerDia = hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0') + '-01';
    $('#filtroDesde').val(primerDia);
    $('#filtroHasta').val(hoy.toISOString().substring(0, 10));

    // Cargar categorías en filtro
    ajaxPost('acciones_catalogos.php', { accion: 'obtenerCategorias', solo_activas: 0 }, function (err, res) {
        if (!err && res && res.success) {
            res.categorias.forEach(function (c) {
                $('#filtroCategoria').append('<option value="' + c.id + '">' + escHtml(c.nombre) + '</option>');
            });
        }
    });

    generarReporte();
    $('#btnGenerar').on('click', generarReporte);
});

function generarReporte() {
    ajaxPost('acciones_tickets.php', {
        accion: 'obtenerEstadisticas',
        es_bi: 1,
        fecha_desde: $('#filtroDesde').val(),
        fecha_hasta: $('#filtroHasta').val(),
        id_categoria: $('#filtroCategoria').val(),
        modo_reporte: 1
    }, function (err, res) {
        if (err || !res || !res.success) { mostrarToast('Error al cargar reporte.', 'danger'); return; }
        var d = res.datos;
        $('#rTotal').text(d.total || 0);
        $('#rResueltos').text(d.resueltos || 0);
        $('#rAbiertos').text((d.total - d.resueltos - d.cerrados) || 0);
        $('#rPromedio').text(d.promedio_dias ? d.promedio_dias + ' días' : '—');

        renderChart('gMeses',      'bar', d.meses.map(function (m)      { return m.mes; }),      d.meses.map(function (m)      { return m.total; }), 'Tickets por Mes', messRgba('accent', 0.7));
        renderChart('gCategorias', 'bar', d.categorias.map(function (c) { return c.nombre; }),  d.categorias.map(function (c) { return c.total; }), 'Por Categoría',   messRgba('info', 0.7));
        renderChart('gAgentes',    'bar', d.agentes.map(function (a)    { return a.agente; }),  d.agentes.map(function (a)    { return a.total; }), 'Por Agente',      messRgba('success', 0.7));

        var coloresPrio = ['#a8d5ba', messColor('info'), messColor('warning'), messColor('danger')];
        if (charts['gPrioridades']) charts['gPrioridades'].destroy();
        charts['gPrioridades'] = new Chart(document.getElementById('gPrioridades'), {
            type: 'doughnut',
            data: {
                labels: d.prioridades.map(function (p) { return p.prioridad; }),
                datasets: [{ data: d.prioridades.map(function (p) { return p.total; }), backgroundColor: coloresPrio, borderColor: messColor('card-bg'), borderWidth: 2 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: messColor('text') } } } }
        });
    });
}

function renderChart(id, type, labels, data, label, color) {
    if (charts[id]) charts[id].destroy();
    charts[id] = new Chart(document.getElementById(id), {
        type: type,
        data: {
            labels: labels,
            datasets: [{ label: label, data: data, backgroundColor: color, borderRadius: 4 }]
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
}

// Redibujar reporte al cambiar de tema
document.addEventListener('mess:themechange', function () { generarReporte(); });
</script>

<?php include 'pie.php'; ?>
