<?php include("vista/includes/header.php"); ?>

<?php
$ctrl = new CodigosPostalesController();
$paisCtrl = new PaisesController();

$paises = $paisCtrl->listar();

// Filtros
$filtros = [
    'id_pais' => $_GET['id_pais'] ?? '',
    'codigo_postal' => $_GET['codigo_postal'] ?? '',
    'activo' => $_GET['activo'] ?? '',
    'parcial' => $_GET['parcial'] ?? ''
];

$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$limite = 20;

$resultado = $ctrl->listar($filtros, $pagina, $limite);
$items = $resultado['items'];
$total = $resultado['total'];
$paginas = $resultado['paginas'];

// Roles
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
$puedeEditar   = in_array('Administrador', $rolesNombres, true) || in_array('Vendedor', $rolesNombres, true);
$puedeEliminar = in_array('Administrador', $rolesNombres, true);
?>

<style>
.cp-header {
    background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(24, 40, 72, 0.2);
}
.filter-card {
    border: none;
    border-radius: 12px;
    background: #f8f9fa;
    margin-bottom: 2rem;
}
.table-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}
.badge-parcial {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}
</style>

<div class="container-fluid py-4">
    <div class="cp-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1 fw-bold"><i class="bi bi-geo-fill me-2"></i> Homologación de CPs</h2>
            <p class="mb-0 opacity-75">Administración de la fuente de verdad para direcciones</p>
        </div>
        <?php if ($puedeEditar): ?>
        <div class="d-flex gap-2 flex-wrap">
        <?php else: ?>
        <div class="d-flex gap-2 flex-wrap">
        <?php endif; ?>
            <?php
                $exportParams = http_build_query(array_filter([
                    'id_pais'       => $filtros['id_pais'],
                    'codigo_postal' => $filtros['codigo_postal'],
                    'activo'        => $filtros['activo'],
                    'parcial'       => $filtros['parcial'],
                ]));
                $exportUrl = RUTA_URL . 'codigos_postales/exportar' . ($exportParams ? '?' . $exportParams : '');
            ?>
            <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-success fw-bold shadow-sm" title="Exportar a Excel">
                📥 Exportar Excel
            </a>
            <?php if ($puedeEditar): ?>
            <button type="button" class="btn btn-outline-light fw-bold shadow-sm"
                    data-bs-toggle="modal" data-bs-target="#modalImportarCp">
                ⬆ Importar CPs
            </button>
            <a href="<?= RUTA_URL ?>codigos_postales/crear" class="btn btn-light text-primary fw-bold shadow-sm">
                ＋ Nuevo CP
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card filter-card">
        <div class="card-body">
            <form method="GET" action="<?= RUTA_URL ?>codigos_postales" class="row g-3 align-items-end">
                <input type="hidden" name="enlace" value="codigos_postales">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">País</label>
                    <select name="id_pais" class="form-select">
                        <option value="">Todos los países</option>
                        <?php foreach ($paises as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $filtros['id_pais'] == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Código Postal</label>
                    <input type="text" name="codigo_postal" class="form-control" placeholder="Buscar CP..." value="<?= htmlspecialchars($filtros['codigo_postal']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Estado</label>
                    <select name="activo" class="form-select">
                        <option value="">Ver todos</option>
                        <option value="1" <?= $filtros['activo'] == '1' ? 'selected' : '' ?>>Activos</option>
                        <option value="0" <?= $filtros['activo'] == '0' ? 'selected' : '' ?>>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Completitud</label>
                    <select name="parcial" class="form-select">
                        <option value="">Cualquiera</option>
                        <option value="1" <?= $filtros['parcial'] == '1' ? 'selected' : '' ?>>Solo Parciales</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Filtrar</button>
                    <a href="<?= RUTA_URL ?>codigos_postales" class="btn btn-outline-secondary w-100" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>País</th>
                            <th>CP</th>
                            <th>Ubicación (Depto / Muni / Barrio)</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Actualizado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No se encontraron registros</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($items as $item): 
                            $esParcial = AddressService::isPartial($item);
                        ?>
                            <tr>
                                <td class="text-muted small">#<?= $item['id'] ?></td>
                                <td><?= htmlspecialchars($item['nombre_pais']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace fs-6"><?= htmlspecialchars($item['codigo_postal']) ?></span>
                                    <?php if ($esParcial): ?>
                                        <span class="badge badge-parcial" title="Faltan datos de ubicación">Parcial</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="<?= !$item['id_departamento'] ? 'text-danger fw-bold' : '' ?>"><?= htmlspecialchars($item['nombre_departamento'] ?? '[Falta Depto]') ?></span> / 
                                        <span class="<?= !$item['id_municipio'] ? 'text-danger fw-bold' : '' ?>"><?= htmlspecialchars($item['nombre_municipio'] ?? ($item['nombre_localidad'] ?: '[Falta Muni]')) ?></span> / 
                                        <span class="text-muted"><?= htmlspecialchars($item['nombre_barrio'] ?? '-') ?></span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input btn-toggle" type="checkbox" role="switch" 
                                               data-id="<?= $item['id'] ?>" 
                                               <?= $item['activo'] ? 'checked' : '' ?>
                                               <?= !$puedeEditar ? 'disabled' : '' ?>>
                                    </div>
                                </td>
                                <td class="text-center small text-muted">
                                    <?= date('d/m/Y H:i', strtotime($item['updated_at'] ?? $item['created_at'])) ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <?php if ($puedeEditar): ?>
                                            <a href="<?= RUTA_URL ?>codigos_postales/editar/<?= $item['id'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                                ✏
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($puedeEliminar): ?>
                                            <button type="button" class="btn btn-sm btn-danger btn-eliminar-cp"
                                                    data-id="<?= $item['id'] ?>"
                                                    data-cp="<?= htmlspecialchars($item['codigo_postal']) ?>"
                                                    title="Eliminar">
                                                🗑
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación mejorada -->
            <?php if ($paginas > 1):
                $window   = 2;              // páginas a cada lado de la actual
                $baseParams = array_merge($filtros, ['enlace' => 'codigos_postales']);

                function cpPageUrl($baseParams, $p) {
                    return '?' . http_build_query(array_merge($baseParams, ['pagina' => $p]));
                }

                // Construir set de páginas visibles
                $visible = [];
                $visible[] = 1;
                for ($i = max(2, $pagina - $window); $i <= min($paginas - 1, $pagina + $window); $i++) $visible[] = $i;
                $visible[] = $paginas;
                $visible = array_unique($visible);
                sort($visible);
            ?>
            <nav class="mt-4" aria-label="Paginación">
              <ul class="pagination pagination-sm justify-content-center flex-wrap gap-1 mb-0">

                <!-- Anterior -->
                <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                  <a class="page-link rounded" href="<?= cpPageUrl($baseParams, $pagina - 1) ?>">
                    <i class="bi bi-chevron-left"></i>
                  </a>
                </li>

                <?php $prev = null; foreach ($visible as $p):
                    if ($prev !== null && $p - $prev > 1): ?>
                      <li class="page-item disabled"><span class="page-link border-0 bg-transparent">…</span></li>
                    <?php endif; ?>
                    <li class="page-item <?= $p == $pagina ? 'active' : '' ?>">
                      <a class="page-link rounded" href="<?= cpPageUrl($baseParams, $p) ?>"><?= $p ?></a>
                    </li>
                <?php $prev = $p; endforeach; ?>

                <!-- Siguiente -->
                <li class="page-item <?= $pagina >= $paginas ? 'disabled' : '' ?>">
                  <a class="page-link rounded" href="<?= cpPageUrl($baseParams, $pagina + 1) ?>">
                    <i class="bi bi-chevron-right"></i>
                  </a>
                </li>

              </ul>
              <p class="text-center text-muted small mt-2 mb-0">
                Página <?= $pagina ?> de <?= $paginas ?> &nbsp;·&nbsp; <?= number_format($total) ?> registros
              </p>
            </nav>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Active Status
    document.querySelectorAll('.btn-toggle').forEach(btn => {
        btn.addEventListener('change', function() {
            const id = this.getAttribute('data-id');
            const status = this.checked;
            
            fetch(`<?= RUTA_URL ?>codigos_postales/toggle/${id}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    this.checked = !status;
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .catch(err => {
                this.checked = !status;
                Swal.fire('Error', 'No se pudo comunicar con el servidor', 'error');
            });
        });
    });

    // ── Eliminar CP ──────────────────────────────────────────────
    document.querySelectorAll('.btn-eliminar-cp').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const cp = this.dataset.cp;

            Swal.fire({
                title: `¿Eliminar CP ${cp}?`,
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sí, eliminar',
            }).then(result => {
                if (!result.isConfirmed) return;

                const row = this.closest('tr');

                fetch(`<?= RUTA_URL ?>codigos_postales/eliminar/${id}`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        row.style.transition = 'opacity .3s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                        Swal.fire({ icon: 'success', title: 'Eliminado', text: res.message, timer: 1800, showConfirmButton: false });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error'));
            });
        });
    });
});
</script>

<?php if ($puedeEditar): ?>
<!-- ══════════════════════════════════════════════════════════════
     MODAL IMPORTAR CPs — 3 pasos
══════════════════════════════════════════════════════════════ -->
<style>
/* ── Stepper ── */
.wizard-stepper { display:flex; align-items:center; justify-content:center; margin-bottom:1.5rem; gap:0; }
.step-indicator { display:flex; flex-direction:column; align-items:center; position:relative; flex:1; }
.step-indicator .step-circle {
    width:36px; height:36px; border-radius:50%;
    background:#e9ecef; color:#6c757d; font-weight:700;
    display:flex; align-items:center; justify-content:center;
    font-size:.9rem; border:2px solid #dee2e6; transition:.3s;
    z-index:1;
}
.step-indicator.active .step-circle  { background:#4b6cb7; color:#fff; border-color:#4b6cb7; }
.step-indicator.completed .step-circle{ background:#198754; color:#fff; border-color:#198754; }
.step-indicator .step-label { font-size:.7rem; color:#6c757d; margin-top:.25rem; white-space:nowrap; }
.step-indicator.active   .step-label  { color:#4b6cb7; font-weight:600; }
.step-indicator.completed .step-label { color:#198754; }
.step-connector { flex:1; height:2px; background:#dee2e6; margin:-18px 0 0 0; }
/* ── Tabla preview ── */
#cpPreviewTable { font-size:.78rem; }
/* ── Opción avanzada ── */
.import-opts label { font-size:.85rem; }
</style>

<div class="modal fade" id="modalImportarCp" tabindex="-1" aria-labelledby="modalImportarCpLabel"
     aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header" style="background:linear-gradient(135deg,#4b6cb7,#182848);color:#fff;">
                <h5 class="modal-title mb-0" id="modalImportarCpLabel">
                    <i class="bi bi-upload me-2"></i>Importar Códigos Postales
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

                <!-- Stepper -->
                <div class="wizard-stepper">
                    <div class="step-indicator active" id="si-1">
                        <div class="step-circle">1</div>
                        <span class="step-label">Cargar archivo</span>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step-indicator" id="si-2">
                        <div class="step-circle">2</div>
                        <span class="step-label">Vista previa</span>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step-indicator" id="si-3">
                        <div class="step-circle">3</div>
                        <span class="step-label">Resultado</span>
                    </div>
                </div>

                <!-- ══ PASO 1 : Upload ══ -->
                <div class="wizard-step" id="wizardStep1">
                    <form id="formCpPreview" enctype="multipart/form-data">
                        <input type="hidden" name="enlace" value="codigos_postales/import/preview">

                        <!-- Zona de subida -->
                        <div class="border rounded p-4 text-center mb-3"
                             style="background:#f8f9fa; border-style:dashed !important;">
                            <i class="bi bi-file-earmark-arrow-up" style="font-size:2.5rem;color:#4b6cb7;"></i>
                            <p class="mt-2 mb-3 text-muted small">Arrastra tu archivo o haz clic para seleccionarlo</p>
                            <label class="btn btn-outline-primary" for="cpArchivoInput">
                                <i class="bi bi-folder2-open me-1"></i> Seleccionar archivo
                            </label>
                            <input type="file" id="cpArchivoInput" name="archivo" accept=".csv,.xlsx,.xls"
                                   class="d-none">
                            <p class="mb-0 mt-2 text-muted small" id="cpFileName">Ningún archivo seleccionado</p>
                            <p class="mb-0 text-muted" style="font-size:.7rem;">CSV o XLSX · Máx 10 MB · Hasta 10,000 filas</p>
                        </div>

                        <!-- Alerta de archivo -->
                        <div id="cpAlertaArchivo" class="alert d-none"></div>

                        <!-- Opciones avanzadas -->
                        <div class="accordion mb-3" id="accordionOpciones">
                            <div class="accordion-item border-0 bg-light rounded">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light py-2 small fw-bold"
                                            type="button" data-bs-toggle="collapse"
                                            data-bs-target="#panelOpciones">
                                        <i class="bi bi-sliders me-2"></i>Opciones avanzadas
                                    </button>
                                </h2>
                                <div id="panelOpciones" class="accordion-collapse collapse">
                                    <div class="accordion-body import-opts pt-2">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Modo de importación</label>
                                                <select name="modo" class="form-select form-select-sm">
                                                    <option value="upsert" selected>Upsert (insertar y actualizar)</option>
                                                    <option value="solo_nuevos">Solo nuevos (ignorar existentes)</option>
                                                    <option value="sobrescribir_ubicacion">Sobrescribir ubicación</option>
                                                </select>
                                                <div class="form-text">Cómo tratar los CPs que ya existen.</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Crear geografía faltante</label>
                                                <select name="crear_geo" class="form-select form-select-sm">
                                                    <option value="1" selected>Sí, crear dpto/muni/barrio automáticamente</option>
                                                    <option value="0">No, ignorar si no existe</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label fw-semibold">Activo por defecto</label>
                                                <select name="default_activo" class="form-select form-select-sm">
                                                    <option value="1" selected>1 — Activo</option>
                                                    <option value="0">0 — Inactivo</option>
                                                </select>
                                                <div class="form-text">Cuando la columna 'activo' está vacía.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Referencia de columnas -->
                        <div class="card border-0 bg-light rounded mb-3">
                            <div class="card-body py-2">
                                <p class="mb-2 small fw-bold text-secondary"><i class="bi bi-table me-1"></i>Columnas aceptadas</p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless mb-0" style="font-size:.78rem;">
                                        <thead class="text-muted">
                                            <tr><th>Campo</th><th>Sinónimos aceptados</th><th>Requerido</th></tr>
                                        </thead>
                                        <tbody>
                                            <tr><td><code>id_pais</code></td><td>pais, country, id_country</td><td><span class="badge bg-danger">Sí</span></td></tr>
                                            <tr><td><code>codigo_postal</code></td><td>cp, postal_code, zip, zip_code</td><td><span class="badge bg-danger">Sí</span></td></tr>
                                            <tr><td><code>departamento</code></td><td>provincia, state, estado</td><td><span class="badge bg-secondary">No</span></td></tr>
                                            <tr><td><code>municipio</code></td><td>ciudad, distrito, county, city</td><td><span class="badge bg-secondary">No</span></td></tr>
                                            <tr><td><code>barrio</code></td><td>zona, corregimiento, neighborhood, colonia, sector</td><td><span class="badge bg-secondary">No</span></td></tr>
                                            <tr><td><code>nombre_localidad</code></td><td>localidad, referencia, nombre, locality</td><td><span class="badge bg-secondary">No</span></td></tr>
                                            <tr><td><code>activo</code></td><td>active, status</td><td><span class="badge bg-secondary">No</span></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Plantillas -->
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="#" data-cp-plantilla="solo">
                                <i class="bi bi-file-earmark-csv me-1"></i>Plantilla mínima (solo CP)
                            </a>
                            <span class="text-muted">·</span>
                            <a href="#" data-cp-plantilla="completa">
                                <i class="bi bi-file-earmark-csv me-1"></i>Plantilla completa
                            </a>
                        </div>
                    </form>
                </div>

                <!-- ══ PASO 2 : Vista previa ══ -->
                <div class="wizard-step d-none" id="wizardStep2">
                    <!-- Alerta paso 2 -->
                    <div id="cpAlertaPaso2" class="alert d-none"></div>

                    <!-- Tarjetas resumen -->
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="card border-0 bg-light text-center rounded py-2">
                                <div class="fs-3 fw-bold" id="cpSumTotal">0</div>
                                <div class="text-muted small">Total filas</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 text-center rounded py-2" style="background:#d1e7dd;">
                                <div class="fs-3 fw-bold text-success" id="cpSumValidas">0</div>
                                <div class="text-muted small">Válidas</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 text-center rounded py-2" style="background:#f8d7da;">
                                <div class="fs-3 fw-bold text-danger" id="cpSumErrores">0</div>
                                <div class="text-muted small">Errores</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="card border-0 text-center rounded py-2" style="background:#fff3cd;">
                                <div class="fs-3 fw-bold text-warning" id="cpSumWarn">0</div>
                                <div class="text-muted small">Advertencias</div>
                            </div>
                        </div>
                    </div>

                    <!-- Lista de errores -->
                    <div id="cpErroresContainer" class="mb-3 d-none">
                        <h6 class="text-danger mb-2"><i class="bi bi-x-circle me-1"></i>Errores encontrados</h6>
                        <div id="cpErroresLista" class="rounded border overflow-auto" style="max-height:150px;"></div>
                    </div>

                    <!-- Lista de advertencias -->
                    <div id="cpWarnContainer" class="mb-3 d-none">
                        <h6 class="text-warning mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Advertencias</h6>
                        <div id="cpAdvertenciasLista" class="rounded border overflow-auto" style="max-height:120px;"></div>
                    </div>

                    <!-- Tabla preview -->
                    <h6 class="mb-2"><i class="bi bi-table me-1"></i>Vista previa (primeras 50 filas)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover border rounded" id="cpPreviewTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th><th>Status</th><th>País</th><th>CP</th>
                                    <th>Departamento</th><th>Municipio</th><th>Barrio</th>
                                    <th>Localidad</th><th>Activo</th>
                                </tr>
                            </thead>
                            <tbody id="cpPreviewTbody">
                                <tr><td colspan="9" class="text-center py-3 text-muted">Cargando vista previa...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ══ PASO 3 : Resultado ══ -->
                <div class="wizard-step d-none" id="wizardStep3">
                    <div class="text-center mb-4" id="cpResEstado"></div>
                    <div class="row g-3 mb-4">
                        <div class="col-6 col-md-2">
                            <div class="card border-0 bg-light text-center rounded py-2">
                                <div class="fs-4 fw-bold" id="cpResTotal">0</div>
                                <div class="text-muted small">Total</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="card border-0 text-center rounded py-2" style="background:#d1e7dd;">
                                <div class="fs-4 fw-bold text-success" id="cpResInsertadas">0</div>
                                <div class="text-muted small">Insertadas</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="card border-0 text-center rounded py-2" style="background:#cfe2ff;">
                                <div class="fs-4 fw-bold text-primary" id="cpResActualizadas">0</div>
                                <div class="text-muted small">Actualizadas</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="card border-0 bg-light text-center rounded py-2">
                                <div class="fs-4 fw-bold text-secondary" id="cpResOmitidas">0</div>
                                <div class="text-muted small">Omitidas</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="card border-0 text-center rounded py-2" style="background:#f8d7da;">
                                <div class="fs-4 fw-bold text-danger" id="cpResFallidas">0</div>
                                <div class="text-muted small">Fallidas</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <div class="card border-0 bg-light text-center rounded py-2">
                                <div class="fs-5 fw-bold" id="cpResTiempo">0s</div>
                                <div class="text-muted small">Tiempo</div>
                            </div>
                        </div>
                    </div>
                    <div id="cpLinkErrores" class="d-none"></div>
                </div>

            </div><!-- /modal-body -->

            <!-- FOOTER -->
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <!-- Volver (solo en paso 2) -->
                    <button type="button" id="btnCpVolver" class="btn btn-outline-secondary"
                            style="display:none;" >
                        <i class="bi bi-arrow-left me-1"></i> Volver
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>

                    <!-- Paso 1 → 2 -->
                    <button type="button" id="btnCpVistaPrev" class="btn btn-primary">
                        <i class="bi bi-eye me-1"></i> Vista Previa
                    </button>

                    <!-- Paso 2 → 3 (disabled por defecto) -->
                    <button type="button" id="btnCpConfirmar" class="btn btn-success" disabled>
                        <i class="bi bi-check-circle me-1"></i> Confirmar e Importar
                    </button>

                    <!-- Paso 3 -->
                    <button type="button" id="btnCpNuevaImport" class="btn btn-primary" style="display:none;">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Nueva Importación
                    </button>
                </div>
            </div>

        </div>
    </div>
</div><!-- /modal -->
<?php endif; ?>

<!-- JS del wizard -->
<script src="<?= RUTA_URL ?>vista/js/importar_cp.js"></script>

<!-- Controlar visibilidad de botones del footer según el paso activo -->
<script>
(function(){
    var modal = document.getElementById('modalImportarCp');
    if (!modal) return;
    var btnVP  = document.getElementById('btnCpVistaPrev');
    var btnCon = document.getElementById('btnCpConfirmar');
    var btnNue = document.getElementById('btnCpNuevaImport');
    var btnVol = document.getElementById('btnCpVolver');

    // Override irAPaso para manejar visibilidad de botones
    var origInit = window.addEventListener;
    modal.addEventListener('show.bs.modal', function(){
        showBtns(1);
        // Escuchar el paso actual mediante MutationObserver
        var obs = new MutationObserver(function(){
            var pasos = modal.querySelectorAll('.wizard-step');
            var activo = 1;
            pasos.forEach(function(p, i){ if(!p.classList.contains('d-none')) activo = i+1; });
            showBtns(activo);
        });
        modal.querySelectorAll('.wizard-step').forEach(function(el){
            obs.observe(el, { attributes: true, attributeFilter: ['class'] });
        });
        modal.addEventListener('hidden.bs.modal', function(){ obs.disconnect(); }, { once: true });
    });

    function showBtns(paso){
        if (!btnVP || !btnCon || !btnNue || !btnVol) return;
        btnVP.style.display  = paso === 1 ? '' : 'none';
        btnCon.style.display = paso === 2 ? '' : 'none';
        btnNue.style.display = paso === 3 ? '' : 'none';
        btnVol.style.display = paso === 2 ? '' : 'none';
    }
})();
</script>
