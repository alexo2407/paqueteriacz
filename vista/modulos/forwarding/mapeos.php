<?php include("vista/includes/header.php") ?>

<?php
require_once __DIR__ . '/../../../modelo/forwarding.php';
$proveedores = ForwardingModel::obtenerProveedores(true); // Solo activos
$idProvider  = (int)($_GET['id_provider'] ?? 0);
$provActual  = $idProvider ? ForwardingModel::obtenerProveedorPorId($idProvider) : null;
$campos      = $idProvider ? ForwardingModel::obtenerApiFields($idProvider) : [];
?>

<style>
.fwd-card   { border:none; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,0.08); overflow:hidden; }
.fwd-header { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; padding:1.75rem 2rem; }
.fwd-header h3 { margin:0; font-weight:600; }

/* Tabla de campos */
.campo-row  { background:#fff; border-radius:10px; margin-bottom:8px; box-shadow:0 2px 6px rgba(0,0,0,0.06); }
.campo-row td { vertical-align:middle; padding:10px 14px; }
.badge-type { font-size:.75rem; padding:.3em .7em; border-radius:6px; font-weight:600; }
.badge-type.string  { background:#ede9fe; color:#5b21b6; }
.badge-type.int     { background:#dbeafe; color:#1e40af; }
.badge-type.float   { background:#d1fae5; color:#065f46; }
.badge-type.boolean { background:#fef3c7; color:#92400e; }
.badge-type.array   { background:#fce7f3; color:#9d174d; }
.req-badge  { font-size:.7rem; }

/* Panel de selección de proveedor */
.prov-selector { border-radius:12px; }
.prov-selector .prov-item { cursor:pointer; border-radius:10px; padding:10px 16px; transition:all .15s; border:2px solid transparent; }
.prov-selector .prov-item:hover  { background:#f5f3ff; border-color:#a78bfa; }
.prov-selector .prov-item.active { background:#ede9fe; border-color:#7c3aed; }

/* Columnas de mapeo */
.mapping-col { background:#f9fafb; border-radius:12px; padding:16px; border:1px dashed #d1d5db; }
.mapping-col h6 { font-weight:700; color:#374151; margin-bottom:12px; }

/* Test panel */
#testPayloadPanel pre { background:#1e1e2e; color:#cdd6f4; border-radius:10px; padding:16px; font-size:.82rem; max-height:360px; overflow-y:auto; }

.spin-icon { animation: spin .7s linear infinite; display:inline-block; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>

<div class="container-fluid py-3">

    <!-- ── Header ─────────────────────────────────────────── -->
    <div class="card fwd-card mb-4">
        <div class="fwd-header">
            <div class="row align-items-center">
                <div class="col-md-7 mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-diagram-3 fs-3"></i>
                        </div>
                        <div>
                            <h3>Mapeos de Campos</h3>
                            <p class="mb-0 opacity-75">Configura qué campo interno del sistema corresponde a cada campo requerido por la API del proveedor dinámico</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="d-flex justify-content-md-end gap-2 mt-3 mt-md-0 flex-wrap">
                        <a href="<?= RUTA_URL ?>forwarding/proveedores" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.4);border-radius:10px;">
                            <i class="bi bi-building me-1"></i> Proveedores
                        </a>
                        <a href="<?= RUTA_URL ?>forwarding" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.4);border-radius:10px;">
                            <i class="bi bi-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- ── Columna Izquierda: Selección de proveedor ──── -->
        <div class="col-md-3">
            <div class="card fwd-card">
                <div class="card-body p-3">
                    <h6 class="fw-bold text-muted mb-3"><i class="bi bi-building me-1"></i> Proveedor</h6>
                    <div class="prov-selector d-flex flex-column gap-2">
                        <?php if (empty($proveedores)): ?>
                            <p class="text-muted small text-center py-3">No hay proveedores activos</p>
                        <?php else: ?>
                            <?php foreach ($proveedores as $p): ?>
                                <div class="prov-item <?= $idProvider === (int)$p['id'] ? 'active' : '' ?>"
                                     onclick="seleccionarProveedor(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nombre']) ?>', '<?= htmlspecialchars($p['payload_format'] ?? 'json') ?>')">
                                    <div class="fw-semibold"><?= htmlspecialchars($p['nombre']) ?></div>
                                    <small class="text-muted">
                                        <code><?= htmlspecialchars($p['slug']) ?></code>
                                        · <span class="badge badge-type <?= $p['payload_format'] ?? 'json' ?>"><?= strtoupper($p['payload_format'] ?? 'json') ?></span>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Columna Central: Campos de la API ──────────── -->
        <div class="col-md-5">
            <div class="card fwd-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0" id="lblProveedor">
                            <?= $provActual ? '📦 ' . htmlspecialchars($provActual['nombre']) : 'Selecciona un proveedor' ?>
                        </h6>
                        <button class="btn btn-sm btn-primary" id="btnAgregarCampo" style="border-radius:8px;display:<?= $idProvider ? 'inline-flex' : 'none' ?>;"
                                data-bs-toggle="modal" data-bs-target="#modalCampo">
                            <i class="bi bi-plus me-1"></i> Agregar Campo
                        </button>
                    </div>

                    <div id="tablaCampos">
                        <?php if ($idProvider && empty($campos)): ?>
                            <div class="text-center py-5 text-muted" id="emptyCampos">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                No hay campos definidos para este proveedor.<br>
                                <small>Usa <strong>"+ Agregar Campo"</strong> para comenzar.</small>
                            </div>
                        <?php elseif (!$idProvider): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-arrow-left-circle fs-1 d-block mb-2 opacity-50"></i>
                                Selecciona un proveedor para ver sus campos.
                            </div>
                        <?php else: ?>
                            <table class="table table-sm" id="tblCampos">
                                <thead><tr>
                                    <th>Campo API (path)</th>
                                    <th>Tipo</th>
                                    <th class="text-center">Req.</th>
                                    <th>Mapeo Interno</th>
                                    <th></th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($campos as $c): ?>
                                <tr class="campo-row" id="row-campo-<?= $c['id'] ?>">
                                    <td>
                                        <code class="fw-semibold text-primary"><?= htmlspecialchars($c['field_path']) ?></code>
                                        <div class="text-muted small"><?= htmlspecialchars($c['label']) ?></div>
                                    </td>
                                    <td><span class="badge badge-type <?= $c['field_type'] ?>"><?= $c['field_type'] ?></span></td>
                                    <td class="text-center">
                                        <?php if ($c['is_required']): ?>
                                            <span class="badge bg-danger req-badge">●</span>
                                        <?php else: ?>
                                            <span class="text-muted">–</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['internal_key']): ?>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($c['internal_key']) ?></span>
                                            <?php if ($c['transform_rule']): ?>
                                                <span class="badge bg-warning text-dark ms-1 small"><?= htmlspecialchars($c['transform_rule']) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>Sin mapear</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary btn-mapear-campo"
                                                data-id="<?= $c['id'] ?>"
                                                data-path="<?= htmlspecialchars($c['field_path']) ?>"
                                                data-label="<?= htmlspecialchars($c['label']) ?>"
                                                data-internal="<?= htmlspecialchars($c['internal_key'] ?? '') ?>"
                                                data-transform="<?= htmlspecialchars($c['transform_rule'] ?? '') ?>"
                                                title="Configurar Mapeo"
                                                style="border-radius:6px;width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;">
                                            <i class="bi bi-link-45deg"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger btn-eliminar-campo"
                                                data-id="<?= $c['id'] ?>"
                                                data-label="<?= htmlspecialchars($c['label']) ?>"
                                                title="Eliminar"
                                                style="border-radius:6px;width:30px;height:30px;padding:0;display:inline-flex;align-items:center;justify-content:center;">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Columna Derecha: Test de Payload ────────────── -->
        <div class="col-md-4">
            <div class="card fwd-card mb-4" id="testPayloadPanel">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-code-square me-1 text-primary"></i> Probar Payload</h6>
                    <p class="text-muted small mb-3">Construye el payload con el mapeo actual sin enviarlo a la API.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">ID de Pedido (opcional)</label>
                        <input type="number" id="testPedidoId" class="form-control form-control-sm" placeholder="0 = pedido simulado" min="0">
                    </div>
                    <button class="btn btn-primary w-100" id="btnTestPayload" onclick="testPayload()" style="border-radius:10px;" <?= !$idProvider ? 'disabled' : '' ?>>
                        <i class="bi bi-play-fill me-1"></i> Generar Payload
                    </button>
                    <div id="testPayloadResult" class="mt-3" style="display:none;">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fw-semibold small" id="testPayloadStatus"></span>
                            <button class="btn btn-sm btn-outline-secondary" onclick="copiarPayload()" style="border-radius:6px;">
                                <i class="bi bi-clipboard me-1"></i>Copiar
                            </button>
                        </div>
                        <pre id="testPayloadJson"></pre>
                    </div>
                </div>
            </div>

            <!-- Leyenda de campos internos -->
            <div class="card fwd-card">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-1 text-info"></i> Campos del Sistema</h6>
                    <p class="text-muted small mb-2">Claves disponibles para mapear desde <code>$pedido</code>:</p>
                    <div id="listaCamposInternos">
                        <div class="text-center text-muted py-2 small"><i class="bi bi-arrow-repeat spin-icon"></i> Cargando...</div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /row -->
</div>

<!-- ── Modal: Agregar Campo de API ──────────────────────────── -->
<div class="modal fade" id="modalCampo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border:none;border-radius:16px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:16px 16px 0 0;">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i> Agregar Campo de API</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Ruta del Campo (field_path) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="campoPath" placeholder="shipment_destination.address  ó  contains[].name">
                        <div class="form-text">Usa dot-notation para campos anidados. Usa <code>[]</code> para arreglos de productos.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Etiqueta / Descripción <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="campoLabel" placeholder="Dirección de entrega">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tipo de Dato</label>
                        <select class="form-select" id="campoType">
                            <option value="string">string</option>
                            <option value="int">int</option>
                            <option value="float">float</option>
                            <option value="boolean">boolean</option>
                            <option value="array">array</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">¿Requerido?</label>
                        <select class="form-select" id="campoRequired">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Valor por Defecto <small class="text-muted">(si el campo interno está vacío)</small></label>
                        <input type="text" class="form-control" id="campoDefault" placeholder="">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarCampo()"><i class="bi bi-check me-1"></i>Guardar Campo</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: Mapear Campo ──────────────────────────────────── -->
<div class="modal fade" id="modalMapeo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border:none;border-radius:16px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;border-radius:16px 16px 0 0;">
                <h5 class="modal-title"><i class="bi bi-link-45deg me-2"></i> Mapear Campo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="mapeoApiFieldId">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="mapping-col">
                            <h6><i class="bi bi-box-arrow-in-right me-1 text-purple"></i>Campo de la API (Externo)</h6>
                            <code class="fs-6 fw-bold text-primary" id="mapeoFieldPath"></code>
                            <div class="text-muted small mt-1" id="mapeoFieldLabel"></div>
                        </div>
                    </div>
                    <div class="col-12 text-center fs-4 text-muted">⇅</div>
                    <div class="col-12">
                        <div class="mapping-col">
                            <h6><i class="bi bi-database me-1 text-info"></i>Campo del Sistema (Interno)</h6>
                            <select class="form-select" id="mapeoInternalKey">
                                <option value="">— Selecciona el campo del sistema —</option>
                                <!-- Se puebla por JS -->
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Transformación <small class="text-muted">(opcional)</small></label>
                        <select class="form-select" id="mapeoTransform">
                            <option value="">Sin transformación</option>
                            <option value="to_int">to_int — Convertir a entero</option>
                            <option value="to_float">to_float — Convertir a decimal</option>
                            <option value="to_bool">to_bool — Convertir a booleano</option>
                            <option value="upper">upper — Texto en mayúsculas</option>
                            <option value="lower">lower — Texto en minúsculas</option>
                            <option value="limit:50">limit:50 — Limitar a 50 caracteres</option>
                            <option value="limit:100">limit:100 — Limitar a 100 caracteres</option>
                            <option value="limit:255">limit:255 — Limitar a 255 caracteres</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="guardarMapeo()"><i class="bi bi-check me-1"></i>Guardar Mapeo</button>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php") ?>

<script>
const BASE        = '<?= RUTA_URL ?>';
const ID_PROVIDER = <?= $idProvider ?: 0 ?>;
let camposInternos = [];

// ── Inicialización ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    cargarCamposInternos();
});

// ── Selección de Proveedor ──────────────────────────────────
function seleccionarProveedor(id, nombre, fmt) {
    window.location.href = BASE + 'forwarding/mapeos?id_provider=' + id;
}

// ── Cargar Campos Internos del Sistema ─────────────────────
function cargarCamposInternos() {
    fetch(BASE + 'ajax/forwarding_mapeos.php?accion=campos_internos')
        .then(r => r.json())
        .then(data => {
            camposInternos = data.campos || [];
            renderCamposInternos(camposInternos);
            poblarSelectInterno(camposInternos);
        })
        .catch(() => { document.getElementById('listaCamposInternos').innerHTML = '<p class="text-danger small">Error al cargar campos.</p>'; });
}

function renderCamposInternos(campos) {
    const el = document.getElementById('listaCamposInternos');
    if (!campos.length) { el.innerHTML = '<p class="text-muted small">Sin campos disponibles.</p>'; return; }
    el.innerHTML = campos.map(c => `
        <div class="d-flex align-items-center mb-1 gap-2">
            <code class="text-primary small flex-grow-1">${c.key}</code>
            <small class="text-muted">${c.label}</small>
        </div>
    `).join('');
}

function poblarSelectInterno(campos) {
    const sel = document.getElementById('mapeoInternalKey');
    if (!sel) return;
    // Limpiar y re-poblar
    sel.innerHTML = '<option value="">— Selecciona el campo del sistema —</option>';
    campos.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.key;
        opt.textContent = `${c.key}  —  ${c.label}`;
        sel.appendChild(opt);
    });
}

// ── Guardar nuevo Campo de API ─────────────────────────────
function guardarCampo() {
    const path  = document.getElementById('campoPath').value.trim();
    const label = document.getElementById('campoLabel').value.trim();
    if (!path || !label) { Swal.fire({ icon:'warning', title:'Campos requeridos', text:'Completa la ruta y la etiqueta.' }); return; }

    const body = new FormData();
    body.append('accion', 'agregar_campo');
    body.append('id_provider', ID_PROVIDER);
    body.append('field_path', path);
    body.append('label', label);
    body.append('field_type', document.getElementById('campoType').value);
    body.append('is_required', document.getElementById('campoRequired').value);
    body.append('default_value', document.getElementById('campoDefault').value);

    fetch(BASE + 'ajax/forwarding_mapeos.php', { method:'POST', body, credentials:'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon:'success', title:'Campo agregado', timer:1200, showConfirmButton:false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon:'error', title:'Error', text: data.message });
            }
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error de red', text: err.message }));
}

// ── Eliminar Campo ─────────────────────────────────────────
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-eliminar-campo');
    if (!btn) return;
    const id    = btn.dataset.id;
    const label = btn.dataset.label;

    Swal.fire({
        title:`¿Eliminar campo?`,
        html:`Se borrará <strong>${label}</strong> y su mapeo asociado.`,
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#ef4444',
        confirmButtonText:'Eliminar',
        cancelButtonText:'Cancelar'
    }).then(r => {
        if (!r.isConfirmed) return;
        const body = new FormData();
        body.append('accion', 'eliminar_campo');
        body.append('id', id);
        fetch(BASE + 'ajax/forwarding_mapeos.php', { method:'POST', body, credentials:'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`row-campo-${id}`)?.remove();
                    Swal.fire({ icon:'success', title:'Eliminado', timer:1000, showConfirmButton:false });
                } else {
                    Swal.fire({ icon:'error', title:'Error', text: data.message });
                }
            });
    });
});

// ── Abrir Modal de Mapeo ───────────────────────────────────
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-mapear-campo');
    if (!btn) return;

    document.getElementById('mapeoApiFieldId').value   = btn.dataset.id;
    document.getElementById('mapeoFieldPath').textContent = btn.dataset.path;
    document.getElementById('mapeoFieldLabel').textContent = btn.dataset.label;

    // Esperar a que se pueble el select antes de seleccionar el valor
    setTimeout(() => {
        const sel = document.getElementById('mapeoInternalKey');
        sel.value = btn.dataset.internal || '';
        document.getElementById('mapeoTransform').value = btn.dataset.transform || '';
    }, 50);

    new bootstrap.Modal(document.getElementById('modalMapeo')).show();
});

// ── Guardar Mapeo ──────────────────────────────────────────
function guardarMapeo() {
    const idApiField   = document.getElementById('mapeoApiFieldId').value;
    const internalKey  = document.getElementById('mapeoInternalKey').value;
    const transform    = document.getElementById('mapeoTransform').value;

    if (!internalKey) { Swal.fire({ icon:'warning', title:'Selecciona un campo del sistema' }); return; }

    const body = new FormData();
    body.append('accion', 'guardar_mapeo');
    body.append('id_api_field', idApiField);
    body.append('internal_key', internalKey);
    body.append('transform_rule', transform);

    fetch(BASE + 'ajax/forwarding_mapeos.php', { method:'POST', body, credentials:'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon:'success', title:'Mapeo guardado', timer:1200, showConfirmButton:false })
                    .then(() => location.reload());
            } else {
                Swal.fire({ icon:'error', title:'Error', text: data.message });
            }
        })
        .catch(err => Swal.fire({ icon:'error', title:'Error de red', text: err.message }));
}

// ── Test de Payload ────────────────────────────────────────
function testPayload() {
    const btn    = document.getElementById('btnTestPayload');
    const panel  = document.getElementById('testPayloadResult');
    const pre    = document.getElementById('testPayloadJson');
    const status = document.getElementById('testPayloadStatus');
    const pedidoId = document.getElementById('testPedidoId').value || 0;

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon me-1"></i> Generando...';
    panel.style.display = 'none';

    const body = new FormData();
    body.append('accion', 'test_payload');
    body.append('id_provider', ID_PROVIDER);
    body.append('id_pedido', pedidoId);

    fetch(BASE + 'ajax/forwarding_mapeos.php', { method:'POST', body, credentials:'same-origin' })
        .then(r => r.json())
        .then(data => {
            panel.style.display = 'block';
            if (data.success) {
                status.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> ${data.pedido_usado}`;
                pre.textContent  = data.json;
            } else {
                status.innerHTML = `<i class="bi bi-x-circle-fill text-danger me-1"></i> Error`;
                pre.textContent  = data.message;
            }
        })
        .catch(err => {
            panel.style.display = 'block';
            status.innerHTML = '<i class="bi bi-x-circle-fill text-danger me-1"></i> Error de red';
            pre.textContent  = err.message;
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill me-1"></i> Generar Payload';
        });
}

function copiarPayload() {
    const text = document.getElementById('testPayloadJson').textContent;
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({ icon:'success', title:'¡Copiado!', timer:800, showConfirmButton:false });
    });
}
</script>
