<?php
require_once 'conn.php';
require_once 'auth.php';
$noEmpSesion = requiereSesionPage();
requiereBiPage($conn, $noEmpSesion);

$pageTitle = 'Nuevo Ticket';
include 'encabezado.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-plus-circle me-2 text-primary-custom"></i>Nuevo Ticket</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="inicio.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Nuevo Ticket</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card form-card">
            <div class="card-header">
                <i class="fas fa-edit me-1"></i> Información del Ticket
            </div>
            <div class="card-body">
                <form id="formNuevoTicket" novalidate enctype="multipart/form-data">

                    <div class="mb-3">
                        <label for="titulo" class="form-label fw-600">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="titulo" name="titulo"
                               placeholder="Describe brevemente tu solicitud" required maxlength="200">
                        <div class="invalid-feedback">El título es obligatorio.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label for="id_categoria" class="form-label fw-600">Categoría <span class="text-danger">*</span></label>
                            <select class="form-select select2-cat" id="id_categoria" name="id_categoria" required>
                                <option value="">Selecciona una categoría…</option>
                            </select>
                            <div class="invalid-feedback">Selecciona una categoría.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="prioridad" class="form-label fw-600">Prioridad <span class="text-danger">*</span></label>
                            <select class="form-select" id="prioridad" name="prioridad" required>
                                <option value="">Selecciona prioridad…</option>
                                <option value="baja">🟢 Baja</option>
                                <option value="media">🔵 Media</option>
                                <option value="alta">🟡 Alta</option>
                                <option value="urgente">🔴 Urgente</option>
                            </select>
                            <div class="invalid-feedback">Selecciona una prioridad.</div>
                        </div>
                    </div>

                    <div id="bloqueKpi" class="d-none mb-3">
                        <div class="alert alert-info py-2 px-3 mb-2 fs-7">
                            <i class="fas fa-chart-line me-1"></i>Esta categoría requiere información del KPI.
                        </div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label for="kpi_nombre_tablero" class="form-label fw-600">Nombre del tablero <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="kpi_nombre_tablero" maxlength="200"
                                       placeholder="Ej. Ventas Mensuales">
                                <div class="invalid-feedback">Indica el nombre del tablero.</div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="kpi_filtros" class="form-label fw-600">Filtros aplicados <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="kpi_filtros" rows="2"
                                          placeholder="Ej. Año: 2026, Mes: Mayo, Sucursal: Querétaro"></textarea>
                                <div class="invalid-feedback">Describe los filtros aplicados.</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label fw-600">Descripción <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="5"
                                  placeholder="Describe con detalle tu solicitud, el problema o la información requerida…"
                                  required minlength="10"></textarea>
                        <div class="invalid-feedback">La descripción es obligatoria (mínimo 10 caracteres).</div>
                    </div>

                    <div class="mb-3">
                        <label for="link" class="form-label fw-600">Enlace de referencia</label>
                        <input type="url" class="form-control" id="link" name="link"
                               placeholder="https://… (opcional)" maxlength="500">
                        <div class="form-text">Si tu solicitud está relacionada con un documento, dashboard, ticket externo o recurso en línea, pega el enlace aquí.</div>
                        <div class="invalid-feedback">Ingresa una URL válida (debe comenzar con http:// o https://).</div>
                    </div>

                    <div class="mb-4">
                        <label for="adjuntos" class="form-label fw-600">Archivos adjuntos</label>
                        <input type="file" class="form-control" id="adjuntos" name="adjuntos[]" multiple
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.csv">
                        <div class="form-text">Máx. 5 archivos. Tipos permitidos: imágenes, PDF, Office, ZIP, texto. (10 MB c/u)</div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a href="mis_tickets.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </a>
                        <button type="button" class="btn btn-primary" id="btnEnviarTicket" onclick="enviarTicketConKpi()">
                            <i class="fas fa-paper-plane me-1"></i>Enviar Ticket
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
var KPI_ID = null;
var categoriaTipos = {}; // Guardar tipo de cada categoría

$(function () {
    // Cargar categorías activas
    ajaxPost('acciones_catalogos.php', { accion: 'obtenerCategorias', solo_activas: 1 }, function (err, res) {
        if (err || !res || !res.success) return;
        var sel = $('#id_categoria');
        var $sistemas = $('<optgroup label="Sistemas MESS">');
        var $ti       = $('<optgroup label="Departamento TI">');
        var $otros    = $('<optgroup label="Otros alcances">');
        res.categorias.forEach(function (c) {
            if (c.nombre === 'Tableros de KPIs') KPI_ID = String(c.id);
            categoriaTipos[c.id] = c.tipo; // Guardar tipo
            var opt = '<option value="' + c.id + '">' + escHtml(c.nombre) + '</option>';
            if (c.tipo === 'sistema') $sistemas.append(opt);
            else if (c.tipo === 'ti') $ti.append(opt);
            else $otros.append(opt);
        });
        if ($sistemas.children().length) sel.append($sistemas);
        if ($ti.children().length) sel.append($ti);
        if ($otros.children().length)    sel.append($otros);
        sel.select2({
            placeholder: 'Selecciona una categoría…',
            width: '100%',
            templateResult: renderOpcionCategoria,
            templateSelection: renderSeleccionCategoria
        });
        sel.on('change', function () { toggleKpiCampos(); });
    });
});

var TIPO_LABEL_MAP = { 'Sistemas MESS': 'sistema', 'Departamento TI': 'ti', 'Otros alcances': 'otro' };

function renderOpcionCategoria(opt) {
    // Encabezado de optgroup — coloreado por tipo
    if (opt.children) {
        var color = getColorPorTipo(TIPO_LABEL_MAP[opt.text]);
        return $('<strong>').css({ color: color, fontWeight: '700' }).text(opt.text);
    }
    if (!opt.id) return opt.text;
    return opt.text;
}

function renderSeleccionCategoria(opt) {
    if (!opt.id) return opt.text;
    var color = getColorPorTipo(categoriaTipos[opt.id]);
    return $('<span>').append(
        $('<span>').css({ display: 'inline-block', width: '10px', height: '10px',
            borderRadius: '50%', background: color, marginRight: '7px', verticalAlign: 'middle' })
    ).append(document.createTextNode(opt.text));
}

function toggleKpiCampos() {
    var esKpi = (KPI_ID && String($('#id_categoria').val()) === KPI_ID);
    var $bloque = $('#bloqueKpi');
    if (esKpi) {
        $bloque.removeClass('d-none');
        $('#kpi_nombre_tablero, #kpi_filtros').prop('required', true);
    } else {
        $bloque.addClass('d-none');
        $('#kpi_nombre_tablero, #kpi_filtros')
            .prop('required', false)
            .removeClass('is-invalid')
            .val('');
    }
}

function enviarTicketConKpi() {
    var form = document.getElementById('formNuevoTicket');
    if (!form) return;

    // La descripción no puede ser solo espacios (mínimo 10 caracteres reales)
    var $desc = $('#descripcion');
    if ($desc.val().trim().length < 10) {
        $desc.addClass('is-invalid').focus();
        form.classList.add('was-validated');
        return;
    }
    $desc.removeClass('is-invalid');

    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    // Si es KPI, prepender info al campo descripcion (una sola vez)
    var esKpi = !$('#bloqueKpi').hasClass('d-none');
    if (esKpi) {
        var tablero = $('#kpi_nombre_tablero').val().trim();
        var filtros = $('#kpi_filtros').val().trim();
        var descActual = $desc.val();
        if (descActual.indexOf('[KPI] Tablero:') !== 0) {
            $desc.val('[KPI] Tablero: ' + tablero + '\nFiltros aplicados: ' + filtros + '\n\n' + descActual);
        }
    }

    // Advertir si el enlace no pertenece a Messbook (pero permitir continuar)
    var link = ($('#link').val() || '').trim();
    if (link !== '' && link.indexOf('https://messbook.com.mx') !== 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Enlace externo',
            text: 'El enlace no pertenece a Messbook (messbook.com.mx). ¿Deseas continuar de todas formas?',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Revisar enlace',
            confirmButtonColor: messColor('accent')
        }).then(function (r) {
            if (r.isConfirmed) registrarTicket();
        });
        return;
    }

    registrarTicket();
}
</script>

<?php include 'pie.php'; ?>
