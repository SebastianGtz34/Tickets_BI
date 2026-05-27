<?php
require_once 'conn.php';
require_once 'auth.php';
$noEmpSesion = requiereSesionPage();
requiereBiPage($conn, $noEmpSesion);

$pageTitle = 'Gestionar Ticket';
$idTicket  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$idTicket) {
    header('Location: bandeja.php');
    exit;
}
include 'encabezado.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-tasks me-2 text-primary-custom"></i>Gestionar Ticket</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="inicio.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="bandeja.php">Bandeja</a></li>
                <li class="breadcrumb-item active" id="breadFolio">…</li>
            </ol>
        </nav>
    </div>
    <a href="bandeja.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="row g-3">
    <!-- Columna principal -->
    <div class="col-12 col-lg-8">

        <!-- Detalle -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <span id="tituloTicket" class="fw-600">Cargando…</span>
                <span id="estadoBadge"></span>
            </div>
            <div class="card-body">
                <p class="text-muted fs-7 mb-1"><strong>Descripción:</strong></p>
                <p id="descripcionTicket" class="mb-3">—</p>

                <div id="linkTicket" class="d-none mb-3">
                    <p class="text-muted fs-7 mb-1"><strong>Enlace de referencia:</strong></p>
                    <a id="linkTicketAnchor" href="#" target="_blank" rel="noopener" class="text-break"></a>
                </div>

                <div id="adjuntosTicket" class="d-none">
                    <p class="text-muted fs-7 mb-1"><strong>Archivos adjuntos:</strong></p>
                    <div id="listaAdjuntos" class="d-flex flex-wrap gap-2"></div>
                </div>
            </div>
        </div>

        <!-- Comentarios -->
        <div class="card">
            <div class="card-header"><i class="fas fa-comments me-1"></i>Comentarios y Notas</div>
            <div class="card-body">
                <div class="chat-container mb-3" id="comentariosContainer">
                    <p class="text-muted text-center fs-7">Cargando…</p>
                </div>
                <hr>
                <div id="avisoTicketCerrado" class="alert alert-secondary py-2 px-3 mb-2 fs-7 d-none">
                    <i class="fas fa-lock me-1"></i>Este ticket está cerrado. Los comentarios están deshabilitados.
                </div>
                <div>
                    <label class="form-label fw-600 fs-7">Agregar comentario / nota</label>
                    <textarea class="form-control mb-2" id="nuevoComentario" rows="3"
                              placeholder="Escribe tu comentario o nota interna…"></textarea>

                    <div class="mb-2">
                        <input type="file" class="form-control form-control-sm" id="adjuntoComentario"
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt">
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="esInterno" value="1">
                            <label class="form-check-label fs-7" for="esInterno">
                                <i class="fas fa-lock me-1 text-warning"></i>Nota interna (solo BI)
                            </label>
                        </div>
                        <button class="btn btn-primary btn-sm" id="btnAgregarComentario"
                                onclick="agregarComentario(<?= $idTicket ?>, true)">
                            <i class="fas fa-paper-plane me-1"></i>Enviar
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Columna metadatos + controles BI -->
    <div class="col-12 col-lg-4">

        <!-- Metadatos -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle me-1"></i>Información</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th class="ps-3 fs-7 text-muted" width="40%">Folio</th>
                            <td class="pe-3 fs-7 fw-600 text-primary-custom folio-badge" id="metaFolio">—</td></tr>
                        <tr><th class="ps-3 fs-7 text-muted">Estado</th>
                            <td class="pe-3" id="metaEstado">—</td></tr>
                        <tr><th class="ps-3 fs-7 text-muted">Prioridad</th>
                            <td class="pe-3" id="metaPrioridad">—</td></tr>
                        <tr><th class="ps-3 fs-7 text-muted">Categoría</th>
                            <td class="pe-3 fs-7" id="metaCategoria">—</td></tr>
                        <tr><th class="ps-3 fs-7 text-muted">Solicitante</th>
                            <td class="pe-3 fs-7" id="metaSolicitante">—</td></tr>
                        <tr><th class="ps-3 fs-7 text-muted">Asignado a</th>
                            <td class="pe-3 fs-7" id="metaAsignado">—</td></tr>
                        <tr><th class="ps-3 fs-7 text-muted">Creado</th>
                            <td class="pe-3 fs-7" id="metaFecha">—</td></tr>
                        <tr><th class="ps-3 fs-7 text-muted">Actualizado</th>
                            <td class="pe-3 fs-7" id="metaActualizado">—</td></tr>
                        <tr><th class="ps-3 fs-7 text-muted">Cerrado</th>
                            <td class="pe-3 fs-7" id="metaCierre">—</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Controles BI -->
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-sliders-h me-1"></i>Cambiar Estado</div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary btn-sm" onclick="cambiarEstado('en_proceso')">
                        <i class="fas fa-play me-1"></i>Poner En Proceso
                    </button>
                    <button class="btn btn-outline-warning btn-sm" onclick="cambiarEstado('pendiente')">
                        <i class="fas fa-pause me-1"></i>Marcar Pendiente
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="cambiarEstado('resuelto')">
                        <i class="fas fa-check me-1"></i>Marcar Resuelto
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="cerrarTicket()">
                        <i class="fas fa-times-circle me-1"></i>Cerrar Ticket
                    </button>
                </div>
            </div>
        </div>

        <!-- Asignar -->
        <div class="card">
            <div class="card-header"><i class="fas fa-user-cog me-1"></i>Asignar Ticket</div>
            <div class="card-body">
                <select id="selAsignar" multiple class="form-select form-select-sm" style="width:100%"></select>
                <div class="form-text">Hasta 3 ingenieros del equipo BI.</div>
                <button class="btn btn-primary btn-sm w-100 mt-2" onclick="asignarTicket()">
                    <i class="fas fa-user-check me-1"></i>Guardar asignación
                </button>
            </div>
        </div>

    </div>
</div>

<script>
var ID_TICKET = <?= $idTicket ?>;

$(function () {
    cargarEquipoBi(function () {
        cargarDetalleTicket();
    });
    cargarComentarios(ID_TICKET, true);
});

function cargarEquipoBi(cb) {
    ajaxPost('acciones_tickets.php', { accion: 'obtenerEquipoBi' }, function (err, res) {
        var $sel = $('#selAsignar');
        if (!err && res && res.success) {
            res.miembros.forEach(function (m) {
                var label = m.nombre ? (m.nombre + ' (#' + m.noEmpleado + ')') : ('Empleado #' + m.noEmpleado);
                $sel.append('<option value="' + m.noEmpleado + '">' + escHtml(label) + '</option>');
            });
        }
        $sel.select2({
            placeholder: 'Selecciona ingeniero(s)…',
            width: '100%',
            maximumSelectionLength: 3
        });
        if (typeof cb === 'function') cb();
    });
}

function cargarDetalleTicket() {
    ajaxPost('acciones_tickets.php', { accion: 'obtenerTicket', id: ID_TICKET }, function (err, res) {
        if (err || !res || !res.success) { mostrarAlerta('error', 'No se pudo cargar el ticket.'); return; }
        var t = res.ticket;
        document.title = t.folio + ' — Gestionar';
        $('#breadFolio').text(t.folio);
        $('#tituloTicket').text(t.titulo);
        $('#estadoBadge').html(obtenerBadgeEstado(t.estado));
        $('#descripcionTicket').html(escHtml(t.descripcion).replace(/\n/g, '<br>'));

        if (t.link) {
            $('#linkTicketAnchor').attr('href', t.link).text(t.link);
            $('#linkTicket').removeClass('d-none');
        } else {
            $('#linkTicket').addClass('d-none');
        }

        $('#metaFolio').text(t.folio);
        $('#metaEstado').html(obtenerBadgeEstado(t.estado));
        $('#metaPrioridad').html(obtenerBadgePrioridad(t.prioridad));
        $('#metaCategoria').text(t.categoria || '—');
        $('#metaSolicitante').text(t.nombre_solicitante || t.no_empleado_solicitante || '—');
        var nombresAsig = (t.asignados || []).map(function (a) {
            return a.nombre || ('Empleado #' + a.no_empleado);
        });
        $('#metaAsignado').text(nombresAsig.length ? nombresAsig.join(', ') : 'Sin asignar');
        $('#metaFecha').text(formatearFecha(t.fecha_creacion));
        $('#metaActualizado').text(formatearFecha(t.fecha_actualizacion));
        $('#metaCierre').text(t.fecha_cierre ? formatearFecha(t.fecha_cierre) : '—');

        // Preseleccionar asignados en el Select2
        var ids = (t.asignados || []).map(function (a) { return String(a.no_empleado); });
        $('#selAsignar').val(ids).trigger('change');

        // Ticket cerrado: bloquear comentarios
        var cerrado = (t.estado === 'cerrado');
        $('#nuevoComentario, #adjuntoComentario, #esInterno, #btnAgregarComentario').prop('disabled', cerrado);
        $('#avisoTicketCerrado').toggleClass('d-none', !cerrado);

        if (res.adjuntos && res.adjuntos.length > 0) {
            var html = '';
            res.adjuntos.forEach(function (a) {
                html += '<a href="uploads/' + escHtml(a.ruta) + '" target="_blank" class="adjunto-chip">'
                    + '<i class="fas fa-paperclip"></i>' + escHtml(a.nombre_archivo) + '</a>';
            });
            $('#listaAdjuntos').html(html);
            $('#adjuntosTicket').removeClass('d-none');
        }
    });
}

function cambiarEstado(nuevoEstado) {
    ajaxPost('acciones_tickets.php', {
        accion: 'actualizarEstado',
        id: ID_TICKET,
        estado: nuevoEstado,
        no_empleado: getCookie('noEmpleadoBI')
    }, function (err, res) {
        if (err || !res) { mostrarAlerta('error', 'Error de comunicación.'); return; }
        if (res.success) {
            mostrarToast('Estado actualizado a "' + nuevoEstado + '".', 'success');
            cargarDetalleTicket();
        } else {
            mostrarAlerta('error', res.message || 'Error al actualizar estado.');
        }
    });
}

function cerrarTicket() {
    confirmarAccion('El ticket quedará cerrado y no podrá reabrirse fácilmente.', function () {
        ajaxPost('acciones_tickets.php', {
            accion: 'cerrarTicket',
            id: ID_TICKET,
            no_empleado: getCookie('noEmpleadoBI')
        }, function (err, res) {
            if (err || !res) { mostrarAlerta('error', 'Error de comunicación.'); return; }
            if (res.success) {
                mostrarAlerta('success', 'Ticket cerrado correctamente.');
                setTimeout(function () { window.location.href = 'bandeja.php'; }, 2000);
            } else {
                mostrarAlerta('error', res.message || 'Error al cerrar el ticket.');
            }
        });
    });
}

function asignarTicket() {
    var seleccion = $('#selAsignar').val() || [];
    $.ajax({
        url: 'acciones_tickets.php',
        method: 'POST',
        data: { accion: 'asignarTicket', id: ID_TICKET, 'asignados[]': seleccion },
        dataType: 'json',
        success: function (res) {
            if (res && res.success) {
                mostrarToast(seleccion.length ? 'Asignación guardada.' : 'Asignación eliminada.', 'success');
                cargarDetalleTicket();
            } else {
                mostrarAlerta('error', (res && res.message) || 'Error al asignar ticket.');
            }
        },
        error: function () { mostrarAlerta('error', 'Error de comunicación.'); }
    });
}
</script>

<?php include 'pie.php'; ?>
