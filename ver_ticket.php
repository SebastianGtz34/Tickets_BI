<?php
require_once 'conn.php';
require_once 'auth.php';
$noEmpSesion = requiereSesionPage();
requiereBiPage($conn, $noEmpSesion);

$pageTitle = 'Ver Ticket';
$idTicket  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$idTicket) {
    header('Location: mis_tickets.php');
    exit;
}
include 'encabezado.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-ticket-alt me-2 text-primary-custom"></i>Detalle del Ticket</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="inicio.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="mis_tickets.php">Mis Tickets</a></li>
                <li class="breadcrumb-item active" id="breadFolio">…</li>
            </ol>
        </nav>
    </div>
    <a href="mis_tickets.php" class="btn btn-outline-mess-naranja">
        <i class="fas fa-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="row g-3" id="ticketContainer">
    <!-- Columna principal -->
    <div class="col-12 col-lg-8">

        <!-- Detalle del ticket -->
        <div class="card mb-3" id="cardDetalle">
            <div class="card-header d-flex justify-content-between">
                <span id="tituloTicket" class="fw-600">Cargando…</span>
                <span id="estadoBadge"></span>
            </div>
            <div class="card-body">
                <p class="text-muted fs-7 mb-1"><strong>Descripción:</strong></p>
                <p id="descripcionTicket" class="mb-3">—</p>

                <!-- Enlace de referencia -->
                <div id="linkTicket" class="d-none mb-3">
                    <p class="text-muted fs-7 mb-1"><strong>Enlace de referencia:</strong></p>
                    <a id="linkTicketAnchor" href="#" target="_blank" rel="noopener" class="text-break"></a>
                </div>

                <!-- Adjuntos del ticket -->
                <div id="adjuntosTicket" class="d-none">
                    <p class="text-muted fs-7 mb-1"><strong>Archivos adjuntos:</strong></p>
                    <div id="listaAdjuntos" class="d-flex flex-wrap gap-2"></div>
                </div>
            </div>
        </div>

        <!-- Comentarios -->
        <div class="card">
            <div class="card-header"><i class="fas fa-comments me-1"></i>Comentarios</div>
            <div class="card-body">
                <div class="chat-container mb-3" id="comentariosContainer">
                    <p class="text-muted text-center fs-7">Cargando comentarios…</p>
                </div>

                <hr>

                <div id="avisoTicketCerrado" class="alert alert-secondary py-2 px-3 mb-2 fs-7 d-none">
                    <i class="fas fa-lock me-1"></i>Este ticket está cerrado. Los comentarios están deshabilitados.
                </div>

                <!-- Formulario comentario -->
                <div>
                    <label class="form-label fw-600 fs-7">Agregar comentario</label>
                    <textarea class="form-control mb-2" id="nuevoComentario" rows="3"
                              placeholder="Escribe tu comentario… (usa @ para mencionar)"></textarea>
                    <div class="mb-2">
                        <input type="file" class="form-control form-control-sm" id="adjuntoComentario"
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt">
                        <div class="form-text">Adjunto opcional (1 archivo, máx. 10 MB)</div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary btn-sm" id="btnAgregarComentario"
                                onclick="agregarComentario(<?= $idTicket ?>, false)">
                            <i class="fas fa-paper-plane me-1"></i>Enviar
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Columna metadatos -->
    <div class="col-12 col-lg-4">
        <div class="card" id="cardMeta">
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
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
var ID_TICKET = <?= $idTicket ?>;

$(function () {
    cargarDetalleTicket();
    cargarComentarios(ID_TICKET, false);
    initMenciones(ID_TICKET);
});

function cargarDetalleTicket() {
    ajaxPost('acciones_tickets.php', { accion: 'obtenerTicket', id: ID_TICKET }, function (err, res) {
        if (err || !res || !res.success) {
            mostrarAlerta('error', 'No se pudo cargar el ticket.');
            return;
        }
        var t = res.ticket;
        document.title = t.folio + ' — Tickets BI';
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
        var nombresAsig = (t.asignados || []).map(function (a) { return a.nombre || ('Empleado #' + a.no_empleado); });
        $('#metaAsignado').text(nombresAsig.length ? nombresAsig.join(', ') : 'Sin asignar');
        $('#metaFecha').text(formatearFecha(t.fecha_creacion));
        $('#metaActualizado').text(formatearFecha(t.fecha_actualizacion));

        // Ticket cerrado: bloquear comentarios
        var cerrado = (t.estado === 'cerrado');
        $('#nuevoComentario, #adjuntoComentario, #btnAgregarComentario').prop('disabled', cerrado);
        $('#avisoTicketCerrado').toggleClass('d-none', !cerrado);

        if (res.adjuntos && res.adjuntos.length > 0) {
            var html = '';
            res.adjuntos.forEach(function (a) {
                html += '<a href="uploads/' + escHtml(a.ruta) + '" target="_blank" class="adjunto-chip">'
                    + '<i class="fas fa-paperclip"></i>' + escHtml(a.nombre_archivo)
                    + ' <small class="opacity-75">(' + formatearTamano(a.tamano) + ')</small></a>';
            });
            $('#listaAdjuntos').html(html);
            $('#adjuntosTicket').removeClass('d-none');
        }
    });
}
</script>

<?php include 'pie.php'; ?>
