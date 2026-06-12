<?php
// Detalle de ticket — vista slim para iframe en loginMaster.
// Solo requiere sesión. No requiere BI.
require_once 'conn.php';
require_once 'auth.php';
$noEmpSesion = requiereSesionPage();
$idTicket = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$idTicket) {
    header('Location: embed_mis.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Ticket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="css/tribute.css">
    <link rel="stylesheet" href="css/estilos.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body { background: var(--bg); padding: 1rem; }
    </style>
</head>
<body>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fas fa-ticket-alt me-2 text-primary-custom"></i>Detalle del Ticket — <span id="folioHdr">…</span></h5>
    <a href="embed_mis.php" class="btn btn-sm btn-outline-mess-naranja">
        <i class="fas fa-arrow-left me-1"></i>Volver
    </a>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">

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

        <div class="card">
            <div class="card-header"><i class="fas fa-comments me-1"></i>Comentarios</div>
            <div class="card-body">
                <div class="chat-container mb-3" id="comentariosContainer">
                    <p class="text-muted text-center fs-7">Cargando comentarios…</p>
                </div>
                <hr>
                <div id="avisoTicketCerrado" class="alert alert-secondary py-2 px-3 mb-2 fs-7 d-none">
                    <i class="fas fa-lock me-1"></i>Este ticket está cerrado o cancelado. Los comentarios están deshabilitados.
                </div>
                <div>
                    <label class="form-label fw-600 fs-7">Agregar comentario</label>
                    <textarea class="form-control mb-2" id="nuevoComentario" rows="3" placeholder="Escribe tu comentario… (usa @ para mencionar)"></textarea>
                    <div class="mb-2">
                        <input type="file" class="form-control form-control-sm" id="adjuntoComentario"
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt">
                        <div class="form-text">Adjunto opcional (1 archivo, máx. 10 MB)</div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary btn-sm" id="btnAgregarComentario" onclick="enviarComentarioEmbed()">
                            <i class="fas fa-paper-plane me-1"></i>Enviar
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="col-12 col-lg-4">
        <div class="card">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/tribute.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="js/funciones.js"></script>
<script>
var ID_TICKET = <?= $idTicket ?>;

function getCookie(name) {
    const cookies = new URLSearchParams(document.cookie.replace(/; /g, '&'));
    return cookies.get(name) || undefined;
}
function escHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function messColor(token) {
    var v = getComputedStyle(document.body).getPropertyValue('--' + token);
    return (v || '').trim();
}
/* Toast homologado (mismo formato que el resto del sistema) */
function mostrarToast(mensaje, tipo) {
    tipo = tipo || 'primary';
    var id = 'toast_' + Date.now();
    var bgMap = { success:'bg-success', danger:'bg-danger', warning:'bg-warning text-dark', info:'bg-info', primary:'bg-primary' };
    var bg = bgMap[tipo] || 'bg-primary';
    var html = '<div id="' + id + '" class="toast align-items-center text-white ' + bg + ' border-0" role="alert" aria-live="assertive" aria-atomic="true">'
        + '<div class="d-flex"><div class="toast-body">' + mensaje + '</div>'
        + '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>';
    var container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    container.insertAdjacentHTML('beforeend', html);
    var toastEl = new bootstrap.Toast(document.getElementById(id), { delay: 3500 });
    toastEl.show();
    document.getElementById(id).addEventListener('hidden.bs.toast', function () { this.remove(); });
}
function badgeEstado(estado) {
    var map = { nuevo:['badge-nuevo','Nuevo'], en_proceso:['badge-en_proceso','En Proceso'], pendiente:['badge-pendiente','Pendiente'], resuelto:['badge-resuelto','Resuelto'], cerrado:['badge-cerrado','Cerrado'], cancelado:['badge-cancelado','Cancelado'] };
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
function formatearTamano(b) {
    b = parseInt(b) || 0;
    if (b < 1024) return b + ' B';
    if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
    return (b/1048576).toFixed(1) + ' MB';
}

var _embTribute = null, _embTokens = [];

$(function () {
    cargarTicket();
    cargarComentarios();
    initMencionesEmbed();
});

/** Activa el autocompletar "@" en el textarea con el directorio de empleados activos. */
function initMencionesEmbed() {
    var ta = document.getElementById('nuevoComentario');
    if (!ta || typeof Tribute === 'undefined') return;
    $.post('acciones_comentarios.php', { accion: 'obtenerMencionables', id_ticket: ID_TICKET }, function (res) {
        var lista = (res && res.success && res.mencionables) ? res.mencionables : [];
        var values = lista.map(function (m) {
            var nombre = m.nombre || ('Empleado #' + m.noEmpleado);
            return { key: nombre + (parseInt(m.es_bi) ? ' · BI' : ''), value: nombre, id: m.noEmpleado };
        });
        _embTribute = new Tribute({
            values: values,
            lookup: 'key',
            fillAttr: 'value',
            selectTemplate: function (item) { return '@' + item.original.value; },
            menuItemTemplate: function (item) { return item.string; },
            noMatchTemplate: function () { return null; }
        });
        _embTribute.attach(ta);
        ta.addEventListener('tribute-replaced', function (e) {
            var it = e.detail.item.original;
            _embTokens.push({ id: it.id, token: '@' + it.value });
        });
    }, 'json');
}

/** noEmpleado mencionados cuyo "@Nombre" sigue presente en el texto. */
function mencionadosEmbed(texto) {
    var ids = [];
    _embTokens.forEach(function (t) {
        if (texto.indexOf(t.token) !== -1 && ids.indexOf(t.id) === -1) ids.push(t.id);
    });
    return ids;
}

function cargarTicket() {
    $.post('acciones_tickets.php', { accion: 'obtenerTicket', id: ID_TICKET }, function (res) {
        if (!res || !res.success) {
            mostrarToast('No se pudo cargar el ticket.', 'danger');
            return;
        }
        var t = res.ticket;
        $('#folioHdr').text(t.folio);
        $('#tituloTicket').text(t.titulo);
        $('#estadoBadge').html(badgeEstado(t.estado));
        $('#descripcionTicket').html(escHtml(t.descripcion).replace(/\n/g,'<br>'));
        if (t.link) {
            $('#linkTicketAnchor').attr('href', t.link).text(t.link);
            $('#linkTicket').removeClass('d-none');
        }
        $('#metaFolio').text(t.folio);
        $('#metaEstado').html(badgeEstado(t.estado));
        $('#metaPrioridad').html(badgePrioridad(t.prioridad));

        // Mostrar categoría con color por tipo
        if (t.categoria) {
            var color = getColorPorTipo(t.categoria_tipo);
            var badgeHtml = '<span style="background: ' + color + '26; color: ' + color + '; padding: 4px 8px; border-radius: 4px; font-weight: 500;">'
                          + escHtml(t.categoria) + '</span>';
            $('#metaCategoria').html(badgeHtml);
        } else {
            $('#metaCategoria').text('—');
        }

        var nombresAsig = (t.asignados || []).map(function (a) { return a.nombre || ('Empleado #' + a.no_empleado); });
        $('#metaAsignado').text(nombresAsig.length ? nombresAsig.join(', ') : 'Sin asignar');
        $('#metaFecha').text(formatearFecha(t.fecha_creacion));
        $('#metaActualizado').text(formatearFecha(t.fecha_actualizacion));

        // Ticket en estado terminal (cerrado o cancelado): bloquear comentarios
        var terminal = (t.estado === 'cerrado' || t.estado === 'cancelado');
        $('#nuevoComentario, #adjuntoComentario, #btnAgregarComentario').prop('disabled', terminal);
        $('#avisoTicketCerrado').toggleClass('d-none', !terminal);

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
    }, 'json');
}

function cargarComentarios() {
    $.post('acciones_comentarios.php', { accion: 'obtenerComentarios', id_ticket: ID_TICKET }, function (res) {
        if (!res) return;
        var contenedor = document.getElementById('comentariosContainer');
        if (!res.comentarios || res.comentarios.length === 0) {
            contenedor.innerHTML = '<p class="text-muted text-center my-3 fs-7">Sin comentarios aún.</p>';
            return;
        }
        var miEmp = getCookie('noEmpleadoBI') || '';
        var html = '';
        res.comentarios.forEach(function (c) {
            var esMio = (String(c.no_empleado) === String(miEmp));
            var cls = esMio ? 'mine' : 'other';
            var align = esMio ? 'd-flex justify-content-end' : '';
            html += '<div class="' + align + ' mb-2">'
                + '<div class="chat-bubble ' + cls + '">'
                + '<div class="fw-600 fs-8 mb-1">' + escHtml(c.nombre_empleado || c.no_empleado) + '</div>'
                + '<div>' + escHtml(c.comentario).replace(/\n/g,'<br>') + '</div>';
            if (c.menciones && c.menciones.length) {
                html += '<div class="mt-1">';
                c.menciones.forEach(function (m) {
                    html += '<span class="mencion-chip"><i class="fas fa-at"></i>' + escHtml(m.nombre) + '</span>';
                });
                html += '</div>';
            }
            if (c.adjunto) {
                html += '<div class="mt-2"><a href="uploads/' + escHtml(c.adjunto.ruta) + '" target="_blank" class="adjunto-chip">'
                    + '<i class="fas fa-paperclip"></i>' + escHtml(c.adjunto.nombre_archivo) + '</a></div>';
            }
            html += '<div class="chat-meta">' + formatearFecha(c.fecha) + '</div></div></div>';
        });
        contenedor.innerHTML = html;
        contenedor.scrollTop = contenedor.scrollHeight;
    }, 'json');
}

function enviarComentarioEmbed() {
    var texto = $('#nuevoComentario').val().trim();
    if (!texto) {
        mostrarToast('Escribe un comentario primero.', 'warning');
        return;
    }
    var fd = new FormData();
    fd.append('accion', 'agregarComentario');
    fd.append('id_ticket', ID_TICKET);
    fd.append('no_empleado', getCookie('noEmpleadoBI') || '');
    fd.append('comentario', texto);
    mencionadosEmbed(texto).forEach(function (m) { fd.append('mencionados[]', m); });
    var adj = document.getElementById('adjuntoComentario');
    if (adj && adj.files[0]) fd.append('adjunto', adj.files[0]);

    var btn = document.getElementById('btnAgregarComentario');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    $.ajax({
        url: 'acciones_comentarios.php', method: 'POST', data: fd,
        processData: false, contentType: false, dataType: 'json',
        success: function (res) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Enviar';
            if (res.success) {
                $('#nuevoComentario').val('');
                if (adj) adj.value = '';
                _embTokens = [];
                cargarComentarios();
                mostrarToast('Comentario agregado.', 'success');
            } else {
                mostrarToast(res.message || 'Error al enviar.', 'danger');
            }
        },
        error: function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Enviar';
            mostrarToast('Error de comunicación.', 'danger');
        }
    });
}
</script>
</body>
</html>
