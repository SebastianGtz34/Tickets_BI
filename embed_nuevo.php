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
    <title>Nuevo Ticket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="css/estilos.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { background: var(--bg); padding: 1rem; }
        .embed-form-wrap { max-width: 100%; }
    </style>
</head>
<body>

<div class="embed-form-wrap">
    <div class="card form-card">
        <div class="card-header">
            <i class="fas fa-plus-circle me-1 text-primary-custom"></i> Nuevo Ticket
        </div>
        <div class="card-body">
            <form id="formNuevoTicket" novalidate enctype="multipart/form-data">

                <div class="row mb-3 align-items-center">
                    <label for="titulo" class="col-12 col-md-1 col-form-label fw-600">Título <span class="text-danger">*</span></label>
                    <div class="col-12 col-md-11">
                        <input type="text" class="form-control" id="titulo" name="titulo"
                               placeholder="Describe brevemente tu solicitud" required maxlength="200">
                        <div class="invalid-feedback">El título es obligatorio.</div>
                    </div>
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
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3"
                              placeholder="Describe con detalle tu solicitud…"
                              required minlength="10"></textarea>
                    <div class="invalid-feedback">La descripción es obligatoria (mínimo 10 caracteres).</div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6">
                        <label for="link" class="form-label fw-600">Enlace de referencia</label>
                        <input type="url" class="form-control" id="link" name="link"
                               placeholder="https://… (opcional)" maxlength="500">
                        <div class="form-text">Si tu solicitud está relacionada con un documento o recurso en línea, pega el enlace aquí.</div>
                        <div class="invalid-feedback">Ingresa una URL válida (http:// o https://).</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="adjuntos" class="form-label fw-600">Archivos adjuntos</label>
                        <input type="file" class="form-control" id="adjuntos" name="adjuntos[]" multiple
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt,.csv">
                        <div class="form-text">Máx. 5 archivos. (10 MB c/u)</div>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('formNuevoTicket').reset();">
                        <i class="fas fa-eraser me-1"></i>Limpiar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnEnviarTicket" onclick="enviarTicketEmbed()">
                        <i class="fas fa-paper-plane me-1"></i>Enviar Ticket
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
/* Helpers MESS */
function messColor(token) {
    var v = getComputedStyle(document.body).getPropertyValue('--' + token);
    return (v || '').trim();
}
function getCookie(name) {
    const cookies = new URLSearchParams(document.cookie.replace(/; /g, '&'));
    return cookies.get(name) || undefined;
}

var KPI_ID = null;

$(function () {
    // Cargar categorías activas
    $.ajax({
        url: 'acciones_catalogos.php',
        method: 'POST',
        data: { accion: 'obtenerCategorias', solo_activas: 1 },
        dataType: 'json',
        success: function (res) {
            if (!res || !res.success) return;
            var sel = $('#id_categoria');
            var $sistemas = $('<optgroup label="Sistemas MESS">');
            var $otros    = $('<optgroup label="Otros alcances">');
            res.categorias.forEach(function (c) {
                if (c.nombre === 'Tableros de KPIs') KPI_ID = String(c.id);
                var opt = '<option value="' + c.id + '">' + $('<div>').text(c.nombre).html() + '</option>';
                (c.tipo === 'sistema' ? $sistemas : $otros).append(opt);
            });
            if ($sistemas.children().length) sel.append($sistemas);
            if ($otros.children().length)    sel.append($otros);
            sel.select2({ placeholder: 'Selecciona una categoría…', width: '100%' });
            sel.on('change', toggleKpiCampos);
        }
    });
});

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

function enviarTicketEmbed() {
    var form = document.getElementById('formNuevoTicket');

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
            if (r.isConfirmed) enviarTicketEmbedConfirmado();
        });
        return;
    }

    enviarTicketEmbedConfirmado();
}

function enviarTicketEmbedConfirmado() {
    var form = document.getElementById('formNuevoTicket');

    // Si es KPI, prepender info al campo descripcion antes de armar el FormData
    var esKpi = !$('#bloqueKpi').hasClass('d-none');
    if (esKpi) {
        var tablero = $('#kpi_nombre_tablero').val().trim();
        var filtros = $('#kpi_filtros').val().trim();
        var $desc = $('#descripcion');
        var descActual = $desc.val();
        if (descActual.indexOf('[KPI] Tablero:') !== 0) {
            $desc.val('[KPI] Tablero: ' + tablero + '\nFiltros aplicados: ' + filtros + '\n\n' + descActual);
        }
    }

    var fd = new FormData(form);
    fd.append('accion', 'crearTicket');
    fd.append('no_empleado', getCookie('noEmpleadoBI') || '');

    var btn = document.getElementById('btnEnviarTicket');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando…';

    $.ajax({
        url: 'acciones_tickets.php',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Enviar Ticket';
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Ticket creado',
                    text: 'Folio: ' + res.folio,
                    confirmButtonColor: messColor('accent'),
                    timer: 2200, timerProgressBar: true
                }).then(function () {
                    form.reset();
                    form.classList.remove('was-validated');
                    $('#id_categoria').val('').trigger('change');
                    toggleKpiCampos();
                });
            } else {
                Swal.fire({ icon: 'error', text: res.message || 'Error al crear el ticket.', confirmButtonColor: messColor('accent') });
            }
        },
        error: function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Enviar Ticket';
            Swal.fire({ icon: 'error', text: 'Error de comunicación.', confirmButtonColor: messColor('accent') });
        }
    });
}
</script>
</body>
</html>
