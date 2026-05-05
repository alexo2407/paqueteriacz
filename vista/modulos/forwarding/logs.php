<?php include("vista/includes/header.php") ?>

<?php
$usaDataTables = true;
require_once __DIR__ . '/../../../modelo/forwarding.php';
$proveedores = ForwardingModel::obtenerProveedores();
?>

<style>
.fwd-card { border: none; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow: hidden; }
.fwd-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.75rem 2rem; }
.fwd-header h3 { margin: 0; font-weight: 600; }
.log-status { font-size: 0.75rem; padding: 0.25em 0.6em; border-radius: 6px; font-weight: 600; display: inline-block; }
.log-status.success { background: #d1fae5; color: #065f46; }
.log-status.failed { background: #fee2e2; color: #991b1b; }
.log-status.pending { background: #fef3c7; color: #92400e; }
.btn-retry { border-radius: 8px; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; }
.btn-retry:disabled { opacity: .5; cursor: not-allowed; }
.payload-box { background: #1e1e2e; color: #a6e3a1; border-radius: 10px; padding: 1rem; font-family: 'Fira Code', monospace; font-size: 0.78rem; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
.filter-card { background: #f8f9ff; border-radius: 12px; padding: 1rem 1.25rem; border: 1px solid #e5e7eb; }
</style>

<div class="container-fluid py-3">
    <div class="card fwd-card mb-4">
        <div class="fwd-header">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-journal-text fs-3"></i>
                        </div>
                        <div>
                            <h3>Historial de Forwarding</h3>
                            <p class="mb-0 opacity-75">Logs de envíos a proveedores externos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end gap-2 mt-3 mt-md-0">
                        <a href="<?= RUTA_URL ?>forwarding" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.4);border-radius:10px;">
                            <i class="bi bi-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <!-- Filtros -->
            <div class="filter-card mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label form-label-sm fw-semibold mb-1">Proveedor</label>
                        <select class="form-select form-select-sm" id="filterProvider">
                            <option value="">Todos</option>
                            <?php foreach ($proveedores as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm fw-semibold mb-1">Estado</label>
                        <select class="form-select form-select-sm" id="filterStatus">
                            <option value="">Todos</option>
                            <option value="success">Exitoso</option>
                            <option value="failed">Fallido</option>
                            <option value="pending">Pendiente</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm fw-semibold mb-1">Desde</label>
                        <input type="date" class="form-control form-control-sm" id="filterDesde">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm fw-semibold mb-1">Hasta</label>
                        <input type="date" class="form-control form-control-sm" id="filterHasta">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-sm btn-primary w-100" onclick="loadLogs()">
                            <i class="bi bi-funnel me-1"></i>Filtrar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabla de logs -->
            <div class="table-responsive">
                <table id="tblLogs" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Nº Orden</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>HTTP</th>
                            <th>ID Externo</th>
                            <th>Intentos</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="logsBody">
                        <tr><td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-arrow-repeat spin-icon me-2"></i>Cargando logs...
                        </td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación simple -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <small class="text-muted" id="logsPagInfo">-</small>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" id="btnPrev" onclick="changePage(-1)" disabled>
                        <i class="bi bi-chevron-left"></i> Anterior
                    </button>
                    <button class="btn btn-outline-primary" id="btnNext" onclick="changePage(1)">
                        Siguiente <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle -->
<div class="modal fade" id="modalDetail" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border:none;border-radius:16px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:16px 16px 0 0;">
                <h5 class="modal-title"><i class="bi bi-code-slash me-2"></i>Detalle de Forwarding</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Pedido:</strong> <span id="detailOrder">-</span></div>
                    <div class="col-md-4"><strong>Proveedor:</strong> <span id="detailProvider">-</span></div>
                    <div class="col-md-4"><strong>Estado:</strong> <span id="detailStatus">-</span></div>
                </div>
                <div id="detailError" class="alert alert-danger mb-3" style="display:none;">
                    <i class="bi bi-exclamation-triangle me-1"></i><span id="detailErrorMsg"></span>
                </div>
                <div class="mb-3">
                    <h6 class="fw-bold"><i class="bi bi-arrow-up-right me-1"></i>Request</h6>
                    <div class="payload-box" id="detailRequest">-</div>
                </div>
                <div>
                    <h6 class="fw-bold"><i class="bi bi-arrow-down-left me-1"></i>Response</h6>
                    <div class="payload-box" id="detailResponse">-</div>
                </div>
            </div>
            <div class="modal-footer" id="detailFooter" style="border-top:1px solid #e5e7eb;">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-warning btn-sm" id="btnModalRetry" onclick="retryFromModal()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Reintentar este envío
                </button>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php") ?>

<script>
const BASE = '<?= RUTA_URL ?>';
let currentOffset = 0;
const PAGE_SIZE = 50;
let allLogs = [];

function loadLogs() {
    const params = new URLSearchParams();
    const provider = document.getElementById('filterProvider').value;
    const status = document.getElementById('filterStatus').value;
    const desde = document.getElementById('filterDesde').value;
    const hasta = document.getElementById('filterHasta').value;
    if (provider) params.set('id_provider', provider);
    if (status) params.set('status', status);
    if (desde) params.set('fecha_desde', desde);
    if (hasta) params.set('fecha_hasta', hasta);
    params.set('limit', PAGE_SIZE);
    params.set('offset', currentOffset);

    document.getElementById('logsBody').innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-arrow-repeat spin-icon me-2"></i>Cargando...</td></tr>';

    fetch(BASE + 'ajax/forwarding_logs.php?' + params.toString(), { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'No se pudo cargar los logs.' }); return; }
        allLogs = data.data;
        renderLogs(data.data, data.total);
    })
    .catch(err => {
        document.getElementById('logsBody').innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">Error: ${err.message}</td></tr>`;
    });
}

function renderLogs(logs, total) {
    const body = document.getElementById('logsBody');
    if (!logs || logs.length === 0) {
        body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox display-6 opacity-25 d-block mb-2"></i>No hay logs</td></tr>';
        document.getElementById('logsPagInfo').textContent = '0 registros';
        return;
    }

    body.innerHTML = logs.map((l, i) => `
        <tr>
            <td><small>${formatDate(l.created_at)}</small></td>
            <td><span class="badge bg-light text-dark border">#${escHtml(l.numero_orden || '-')}</span></td>
            <td>${escHtml(l.provider_nombre || '-')}</td>
            <td><span class="log-status ${l.status}">${capitalize(l.status)}</span></td>
            <td><code>${l.http_status || '-'}</code></td>
            <td><small>${escHtml(l.external_order_id || '-')}</small></td>
            <td class="text-center">${l.attempts}</td>
            <td class="text-end">
                <div class="d-flex justify-content-end gap-1">
                    <button class="btn btn-sm btn-outline-primary btn-retry" onclick="showDetail(${i})" title="Ver detalle">
                        <i class="bi bi-eye"></i>
                    </button>
                    ${(l.status === 'failed' || l.status === 'pending') ? `
                    <button class="btn btn-sm btn-outline-warning btn-retry" id="btn-retry-${l.id}"
                            onclick="retryLog(${l.id}, this)" title="Reintentar">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>` : ''}
                </div>
            </td>
        </tr>
    `).join('');

    document.getElementById('logsPagInfo').textContent = `Mostrando ${currentOffset + 1}-${currentOffset + logs.length} de ${total}`;
    document.getElementById('btnPrev').disabled = currentOffset === 0;
    document.getElementById('btnNext').disabled = currentOffset + logs.length >= total;
}

function showDetail(idx) {
    const l = allLogs[idx];
    document.getElementById('detailOrder').textContent = '#' + (l.numero_orden || l.id_pedido);
    document.getElementById('detailProvider').textContent = l.provider_nombre || '-';
    document.getElementById('detailStatus').innerHTML = `<span class="log-status ${l.status}">${capitalize(l.status)}</span>`;
    
    const errDiv = document.getElementById('detailError');
    if (l.error_message) { errDiv.style.display = 'block'; document.getElementById('detailErrorMsg').textContent = l.error_message; }
    else { errDiv.style.display = 'none'; }

    document.getElementById('detailRequest').textContent = prettyJson(l.request_payload);
    document.getElementById('detailResponse').textContent = prettyJson(l.response_payload);
    new bootstrap.Modal(document.getElementById('modalDetail')).show();
}

function changePage(dir) {
    currentOffset = Math.max(0, currentOffset + (dir * PAGE_SIZE));
    loadLogs();
}

function formatDate(d) { if (!d) return '-'; const dt = new Date(d); return dt.toLocaleDateString('es', {day:'2-digit',month:'2-digit'}) + ' ' + dt.toLocaleTimeString('es', {hour:'2-digit',minute:'2-digit'}); }
function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function prettyJson(s) { if (!s) return '-'; try { return JSON.stringify(JSON.parse(s), null, 2); } catch { return s; } }

// ── Reintento manual ─────────────────────────────────────────────────────
let currentModalLogId = null;

function showDetail(idx) {
    const l = allLogs[idx];
    currentModalLogId = l.id;
    document.getElementById('detailOrder').textContent    = '#' + (l.numero_orden || l.id_pedido);
    document.getElementById('detailProvider').textContent = l.provider_nombre || '-';
    document.getElementById('detailStatus').innerHTML     = `<span class="log-status ${l.status}">${capitalize(l.status)}</span>`;

    const errDiv = document.getElementById('detailError');
    if (l.error_message) { errDiv.style.display = 'block'; document.getElementById('detailErrorMsg').textContent = l.error_message; }
    else { errDiv.style.display = 'none'; }

    document.getElementById('detailRequest').textContent  = prettyJson(l.request_payload);
    document.getElementById('detailResponse').textContent = prettyJson(l.response_payload);

    // Mostrar/ocultar botón de reintento según estado
    const btnRetry = document.getElementById('btnModalRetry');
    btnRetry.style.display = (l.status === 'failed' || l.status === 'pending') ? 'inline-flex' : 'none';

    new bootstrap.Modal(document.getElementById('modalDetail')).show();
}

function retryFromModal() {
    if (!currentModalLogId) return;
    retryLog(currentModalLogId, document.getElementById('btnModalRetry'), true);
}

function retryLog(logId, btn, fromModal = false) {
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    fetch(BASE + 'ajax/forwarding_logs.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'retry', id: logId })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Reenvío exitoso!',
                text: data.message,
                timer: 2500,
                showConfirmButton: false
            }).then(() => {
                if (fromModal) bootstrap.Modal.getInstance(document.getElementById('modalDetail'))?.hide();
                loadLogs(); // recargar tabla
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Reenvío fallido',
                html: `<p>${data.message}</p><small class="text-muted">Se registró un nuevo intento en el log.</small>`
            }).then(() => loadLogs());
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        Swal.fire({ icon: 'error', title: 'Error de red', text: err.message });
    });
}

// Auto-load on page ready
document.addEventListener('DOMContentLoaded', loadLogs);
</script>
