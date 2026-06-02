/* ─────────────────────────────────────────
   funciones.js — Utilidades globales
   ───────────────────────────────────────── */

/** Lee una cookie por nombre */
function getCookie(name) {
    const cookies = new URLSearchParams(document.cookie.replace(/; /g, '&'));
    return cookies.get(name) || undefined;
}

/** Alerta SweetAlert2 */
function mostrarAlerta(tipo, mensaje) {
    Swal.fire({
        icon: tipo,
        text: mensaje,
        confirmButtonColor: messColor('accent'),
        background: messColor('card-bg'),
        color: messColor('text'),
        timer: tipo === 'success' ? 2000 : undefined,
        timerProgressBar: tipo === 'success'
    });
}

/** Toast Bootstrap 5 */
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

/** Confirmación SweetAlert2 */
function confirmarAccion(mensaje, callback) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: mensaje,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: messColor('accent'),
        cancelButtonColor: messColor('text-muted'),
        background: messColor('card-bg'),
        color: messColor('text'),
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    }).then(function (result) {
        if (result.isConfirmed) callback();
    });
}

/** Badge HTML por estado */
function obtenerBadgeEstado(estado) {
    var map = {
        nuevo:      ['badge-nuevo',      'Nuevo'],
        en_proceso: ['badge-en_proceso', 'En Proceso'],
        pendiente:  ['badge-pendiente',  'Pendiente'],
        resuelto:   ['badge-resuelto',   'Resuelto'],
        cerrado:    ['badge-cerrado',    'Cerrado']
    };
    var data = map[estado] || ['bg-secondary', estado];
    return '<span class="badge ' + data[0] + '">' + data[1] + '</span>';
}

/** Badge HTML por prioridad */
function obtenerBadgePrioridad(prioridad) {
    var map = {
        baja:    ['badge-baja',    'Baja'],
        media:   ['badge-media',   'Media'],
        alta:    ['badge-alta',    'Alta'],
        urgente: ['badge-urgente', 'Urgente']
    };
    var data = map[prioridad] || ['bg-secondary', prioridad];
    return '<span class="badge ' + data[0] + '">' + data[1] + '</span>';
}

/** Validar permisos contra loginMaster */
function validaPermisos(sistema, opcion, callback) {
    var noEmpleado = getCookie('noEmpleadoBI') || '';
    $.post('../loginMaster/acciones_globales.php', {
        accion: 'ValidarPermisos',
        noEmpleado: noEmpleado,
        sistema: sistema,
        opcion: opcion
    }, function (res) {
        // loginMaster devuelve { status:'success', data:[{ cuantos:N }] }
        var tiene = res && res.status === 'success' && res.data && res.data[0] && parseInt(res.data[0].cuantos) > 0;
        if (typeof callback === 'function') callback({ tiene_permiso: tiene });
    }, 'json').fail(function () {
        if (typeof callback === 'function') callback({ tiene_permiso: false });
    });
}

/** Formatear fecha DD/MM/YYYY HH:mm */
function formatearFecha(fecha) {
    if (!fecha) return '—';
    var d = new Date(fecha.replace(' ', 'T'));
    if (isNaN(d)) return fecha;
    var pad = function (n) { return String(n).padStart(2, '0'); };
    return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear()
        + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
}

/** Formatear tamaño en bytes */
function formatearTamano(bytes) {
    bytes = parseInt(bytes) || 0;
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

/** Wrapper $.ajax POST → JSON */
function ajaxPost(url, data, callback) {
    $.ajax({
        url: url,
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function (res) {
            if (typeof callback === 'function') callback(null, res);
        },
        error: function (xhr, status, err) {
            if (typeof callback === 'function') callback(err || status, null);
        }
    });
}

/** Registrar nuevo ticket (con adjuntos via FormData) */
function registrarTicket() {
    var form = document.getElementById('formNuevoTicket');
    if (!form) return;

    // Validación básica HTML5
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
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
                mostrarAlerta('success', 'Ticket ' + res.folio + ' creado correctamente.');
                setTimeout(function () { window.location.href = 'mis_tickets.php'; }, 2000);
            } else {
                mostrarAlerta('error', res.message || 'Error al crear el ticket.');
            }
        },
        error: function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Enviar Ticket';
            mostrarAlerta('error', 'Error de comunicación con el servidor.');
        }
    });
}

/** Cargar comentarios de un ticket */
function cargarComentarios(idTicket, esBi) {
    ajaxPost('acciones_comentarios.php', { accion: 'obtenerComentarios', id_ticket: idTicket, es_bi: esBi ? 1 : 0 }, function (err, res) {
        if (err || !res) return;
        var contenedor = document.getElementById('comentariosContainer');
        if (!contenedor) return;

        if (!res.comentarios || res.comentarios.length === 0) {
            contenedor.innerHTML = '<p class="text-muted text-center my-3 fs-7">Sin comentarios aún.</p>';
            return;
        }

        var miEmpleado = getCookie('noEmpleadoBI') || '';
        var html = '';

        res.comentarios.forEach(function (c) {
            var esMio = (String(c.no_empleado) === String(miEmpleado));
            var isInterno = parseInt(c.es_interno) === 1;
            var cls = isInterno ? 'interno' : (esMio ? 'mine' : 'other');
            var align = esMio ? 'd-flex justify-content-end' : '';
            var icon = isInterno ? ' <span class="badge bg-warning text-dark ms-1 fs-8"><i class="fas fa-lock"></i> Nota interna</span>' : '';

            html += '<div class="' + align + ' mb-2">'
                + '<div class="chat-bubble ' + cls + '">'
                + '<div class="fw-600 fs-8 mb-1">' + escHtml(c.nombre_empleado || c.no_empleado) + icon + '</div>'
                + '<div>' + escHtml(c.comentario).replace(/\n/g, '<br>') + '</div>';

            if (c.adjunto) {
                html += '<div class="mt-2">'
                    + '<a href="uploads/' + escHtml(c.adjunto.ruta) + '" target="_blank" class="adjunto-chip">'
                    + '<i class="fas fa-paperclip"></i>' + escHtml(c.adjunto.nombre_archivo) + '</a></div>';
            }

            html += '<div class="chat-meta">' + formatearFecha(c.fecha) + '</div>'
                + '</div></div>';
        });

        contenedor.innerHTML = html;
        contenedor.scrollTop = contenedor.scrollHeight;
    });
}

/** Agregar comentario */
function agregarComentario(idTicket, esBi) {
    var textoEl = document.getElementById('nuevoComentario');
    var internoEl = document.getElementById('esInterno');
    var adjuntoEl = document.getElementById('adjuntoComentario');
    var texto = textoEl ? textoEl.value.trim() : '';

    if (!texto) { mostrarToast('Escribe un comentario primero.', 'warning'); return; }

    var fd = new FormData();
    fd.append('accion', 'agregarComentario');
    fd.append('id_ticket', idTicket);
    fd.append('no_empleado', getCookie('noEmpleadoBI') || '');
    fd.append('comentario', texto);
    fd.append('es_interno', (internoEl && internoEl.checked) ? 1 : 0);
    if (adjuntoEl && adjuntoEl.files[0]) fd.append('adjunto', adjuntoEl.files[0]);

    var btn = document.getElementById('btnAgregarComentario');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }

    $.ajax({
        url: 'acciones_comentarios.php',
        method: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (res) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Enviar'; }
            if (res.success) {
                if (textoEl) textoEl.value = '';
                if (internoEl) internoEl.checked = false;
                if (adjuntoEl) adjuntoEl.value = '';
                cargarComentarios(idTicket, esBi);
                mostrarToast('Comentario agregado.', 'success');
            } else {
                mostrarAlerta('error', res.message || 'Error al enviar el comentario.');
            }
        },
        error: function () {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Enviar'; }
            mostrarAlerta('error', 'Error de comunicación.');
        }
    });
}

/** Escapa HTML para evitar XSS */
function escHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
