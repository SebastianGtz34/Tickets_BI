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
                        <button type="button" class="btn btn-primary" id="btnEnviarTicket" onclick="registrarTicket()">
                            <i class="fas fa-paper-plane me-1"></i>Enviar Ticket
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    // Cargar categorías activas
    ajaxPost('acciones_catalogos.php', { accion: 'obtenerCategorias', solo_activas: 1 }, function (err, res) {
        if (err || !res || !res.success) return;
        var sel = $('#id_categoria');
        res.categorias.forEach(function (c) {
            sel.append('<option value="' + c.id + '">' + escHtml(c.nombre) + '</option>');
        });
        sel.select2({ placeholder: 'Selecciona una categoría…', width: '100%' });
    });
});
</script>

<?php include 'pie.php'; ?>
