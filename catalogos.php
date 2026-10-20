<?php
require_once 'conn.php';
require_once 'auth.php';
$noEmpSesion = requiereSesionPage();
requiereBiPage($conn, $noEmpSesion);

$pageTitle = 'Catálogos';
include 'encabezado.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-tags me-2 text-primary-custom"></i>Catálogo de Categorías</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="inicio.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Catálogos</li>
            </ol>
        </nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCategoria" onclick="abrirModalNuevo()">
        <i class="fas fa-plus me-1"></i>Nueva Categoría
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaCatalogos" width="100%">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Activo</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyCatalogos">
                    <tr><td colspan="5" class="text-center py-4 text-muted">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Categoría -->
<div class="modal fade" id="modalCategoria" tabindex="-1" aria-labelledby="modalCategoriaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCategoriaLabel">Nueva Categoría</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCategoria" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="catId" name="id" value="">

                    <div class="mb-3">
                        <label for="catNombre" class="form-label fw-600">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="catNombre" name="nombre"
                               required maxlength="100" placeholder="Ej. Control Vehicular">
                        <div class="invalid-feedback">El nombre es obligatorio.</div>
                    </div>

                    <div class="mb-3">
                        <label for="catTipo" class="form-label fw-600">Tipo <span class="text-danger">*</span></label>
                        <select class="form-select" id="catTipo" name="tipo" required>
                            <option value="sistema">Sistemas Messbook / BI</option>
                            <option value="ti">Departamento TI</option>
                            <option value="rrhh">Recursos Humanos</option>
                            <option value="otro">Otro alcance</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="catGrupo" class="form-label fw-600">Grupo <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" class="form-control" id="catGrupo" name="grupo" maxlength="100"
                               list="listaGrupos" placeholder="Ej. Asistencia, Tiempo y Cumplimiento" autocomplete="off">
                        <datalist id="listaGrupos"></datalist>
                        <div class="form-text">Encabezado bajo el que aparece en el alta de ticket. Vacío = se agrupa por tipo.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarCat" onclick="guardarCategoria()">
                        <i class="fas fa-save me-1"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var CATEGORIAS_CAT = {}; // id → categoría cargada, para editar sin pasar textos por el onclick

$(function () {
    cargarCatalogo();
});

function cargarCatalogo() {
    ajaxPost('acciones_catalogos.php', { accion: 'obtenerCategorias', solo_activas: 0 }, function (err, res) {
        var tbody = document.getElementById('tbodyCatalogos');
        if (err || !res || !res.success) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error al cargar categorías.</td></tr>';
            return;
        }
        if (!res.categorias || res.categorias.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Sin categorías registradas.</td></tr>';
            return;
        }
        var html = '';
        var grupos = {};
        CATEGORIAS_CAT = {};
        res.categorias.forEach(function (c) {
            CATEGORIAS_CAT[c.id] = c;
            if (c.grupo) grupos[c.grupo] = true;
            var activoHtml = c.activo == 1
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>';
            var tipoHtml = {
                sistema: '<span class="badge bg-primary">Sistema Messbook / BI</span>',
                ti:      '<span class="badge bg-info">Departamento TI</span>',
                rrhh:    '<span class="badge" style="background:' + getColorPorTipo('rrhh') + '">Recursos Humanos</span>'
            }[c.tipo] || '<span class="badge bg-secondary">Otro</span>';
            var btnToggle = c.activo == 1
                ? '<button class="btn btn-sm btn-outline-warning" onclick="toggleCategoria(' + c.id + ', 0)"><i class="fas fa-ban me-1"></i>Desactivar</button>'
                : '<button class="btn btn-sm btn-outline-success" onclick="toggleCategoria(' + c.id + ', 1)"><i class="fas fa-check me-1"></i>Activar</button>';
            html += '<tr>'
                + '<td class="ps-3 fs-7">' + c.id + '</td>'
                + '<td class="fw-600">' + escHtml(c.nombre)
                    + (c.grupo ? '<div class="fs-7 text-muted fw-normal">' + escHtml(c.grupo) + '</div>' : '')
                + '</td>'
                + '<td class="text-center">' + tipoHtml + '</td>'
                + '<td class="text-center">' + activoHtml + '</td>'
                + '<td class="text-center">'
                    + '<button class="btn btn-sm btn-outline-primary me-1" onclick="abrirModalEditar(' + c.id + ')">'
                    + '<i class="fas fa-edit"></i></button>'
                    + btnToggle
                + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = html;
        // Sugerencias del campo Grupo: los que ya existen, para no reescribirlos distinto
        $('#listaGrupos').empty().append(Object.keys(grupos).sort().map(function (g) {
            return $('<option>').attr('value', g);
        }));
    });
}

function abrirModalNuevo() {
    document.getElementById('formCategoria').classList.remove('was-validated');
    document.getElementById('catId').value = '';
    document.getElementById('catNombre').value = '';
    document.getElementById('catTipo').value = 'sistema';
    document.getElementById('catGrupo').value = '';
    document.getElementById('modalCategoriaLabel').textContent = 'Nueva Categoría';
}

function abrirModalEditar(id) {
    var c = CATEGORIAS_CAT[id];
    if (!c) return;
    document.getElementById('formCategoria').classList.remove('was-validated');
    document.getElementById('catId').value = id;
    document.getElementById('catNombre').value = c.nombre;
    document.getElementById('catTipo').value = ['sistema', 'ti', 'rrhh'].indexOf(c.tipo) !== -1 ? c.tipo : 'otro';
    document.getElementById('catGrupo').value = c.grupo || '';
    document.getElementById('modalCategoriaLabel').textContent = 'Editar Categoría';
    var modal = new bootstrap.Modal(document.getElementById('modalCategoria'));
    modal.show();
}

function guardarCategoria() {
    var form = document.getElementById('formCategoria');
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }

    var id = document.getElementById('catId').value;
    var accion = id ? 'actualizarCategoria' : 'crearCategoria';

    ajaxPost('acciones_catalogos.php', {
        accion: accion,
        id: id,
        nombre: document.getElementById('catNombre').value.trim(),
        tipo: document.getElementById('catTipo').value,
        grupo: document.getElementById('catGrupo').value.trim()
    }, function (err, res) {
        if (err || !res) { mostrarAlerta('error', 'Error de comunicación.'); return; }
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalCategoria')).hide();
            mostrarToast(id ? 'Categoría actualizada.' : 'Categoría creada.', 'success');
            cargarCatalogo();
        } else {
            mostrarAlerta('error', res.message || 'Error al guardar.');
        }
    });
}

function toggleCategoria(id, nuevoActivo) {
    var msg = nuevoActivo ? 'activar' : 'desactivar';
    confirmarAccion('¿Deseas ' + msg + ' esta categoría?', function () {
        ajaxPost('acciones_catalogos.php', { accion: 'toggleCategoria', id: id, activo: nuevoActivo }, function (err, res) {
            if (err || !res) { mostrarAlerta('error', 'Error de comunicación.'); return; }
            if (res.success) {
                mostrarToast('Categoría ' + (nuevoActivo ? 'activada' : 'desactivada') + '.', 'success');
                cargarCatalogo();
            } else {
                mostrarAlerta('error', res.message || 'Error al actualizar.');
            }
        });
    });
}
</script>

<?php include 'pie.php'; ?>
