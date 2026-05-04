<?php include("vista/includes/header.php") ?>

<?php
$usaDataTables = true;
require_once __DIR__ . '/../../../modelo/forwarding.php';
require_once __DIR__ . '/../../../modelo/usuario.php';
$reglas = ForwardingModel::obtenerTodasLasReglas();
$proveedores = ForwardingModel::obtenerProveedores(true);
// id_cliente en pedidos referencia usuarios.id
// Cargar usuarios con roles que envían pedidos vía API (Proveedor + Cliente)
$um = new UsuarioModel();
$clientesProveedor = $um->obtenerUsuariosPorRolNombre(ROL_NOMBRE_PROVEEDOR);
$clientesCliente   = $um->obtenerUsuariosPorRolNombre(ROL_NOMBRE_CLIENTE);
// Mergear y deduplicar por ID
$clientesMap = [];
foreach (array_merge($clientesProveedor, $clientesCliente) as $u) {
    $clientesMap[$u['id']] = $u;
}
$clientes = array_values($clientesMap);
usort($clientes, function($a, $b) { return strcasecmp($a['nombre'], $b['nombre']); });
?>

<style>
.fwd-card { border: none; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow: hidden; }
.fwd-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.75rem 2rem; }
.fwd-header h3 { margin: 0; font-weight: 600; }
.rule-status { font-size: 0.8rem; padding: 0.3em 0.75em; border-radius: 8px; font-weight: 600; }
.rule-status.active { background: #d1fae5; color: #065f46; }
.rule-status.inactive { background: #fee2e2; color: #991b1b; }
.form-switch .form-check-input { width: 3em; height: 1.5em; cursor: pointer; }
.form-switch .form-check-input:checked { background-color: #667eea; border-color: #667eea; }
</style>

<div class="container-fluid py-3">
    <div class="card fwd-card mb-4">
        <div class="fwd-header">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-diagram-3 fs-3"></i>
                        </div>
                        <div>
                            <h3>Reglas de Forwarding</h3>
                            <p class="mb-0 opacity-75">Asigna clientes a proveedores externos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end gap-2 mt-3 mt-md-0">
                        <a href="<?= RUTA_URL ?>forwarding" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.4);border-radius:10px;">
                            <i class="bi bi-arrow-left me-1"></i> Dashboard
                        </a>
                        <button class="btn" style="background:#fff;color:#667eea;border:none;border-radius:10px;font-weight:600;" data-bs-toggle="modal" data-bs-target="#modalRule">
                            <i class="bi bi-plus-circle-fill me-1"></i> Nueva Regla
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <?php if (empty($proveedores)): ?>
            <div class="alert alert-warning d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>No hay proveedores activos. <a href="<?= RUTA_URL ?>forwarding/proveedores">Crea uno primero</a>.</div>
            </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table id="tblRules" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Proveedor Externo</th>
                            <th>Estado</th>
                            <th class="text-center">Activo</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reglas as $r): ?>
                        <tr>
                            <td><span class="badge bg-light text-dark border">#<?= $r['id'] ?></span></td>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($r['cliente_nombre'] ?? 'ID:' . $r['id_cliente']) ?></td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <i class="bi bi-building me-1"></i><?= htmlspecialchars($r['provider_nombre'] ?? $r['provider_slug'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span class="rule-status <?= $r['activo'] ? 'active' : 'inactive' ?>">
                                    <?= $r['activo'] ? '● Activa' : '● Inactiva' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center mb-0">
                                    <input class="form-check-input toggle-rule" type="checkbox"
                                           data-id="<?= $r['id'] ?>"
                                           <?= $r['activo'] ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger btn-delete-rule" data-id="<?= $r['id'] ?>"
                                        title="Eliminar" style="border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nueva Regla -->
<div class="modal fade" id="modalRule" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border:none;border-radius:16px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:16px 16px 0 0;">
                <h5 class="modal-title"><i class="bi bi-diagram-3 me-2"></i>Nueva Regla de Forwarding</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Cliente</label>
                    <select class="form-select" id="ruleCliente">
                        <option value="">— Seleccionar cliente —</option>
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?> (ID: <?= $c['id'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Proveedor Externo</label>
                    <select class="form-select" id="ruleProvider">
                        <option value="">— Seleccionar proveedor —</option>
                        <?php foreach ($proveedores as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (<?= $p['slug'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Al crear esta regla, todos los pedidos futuros de este cliente serán reenviados automáticamente al proveedor seleccionado.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveRule()">
                    <i class="bi bi-check-lg me-1"></i>Crear Regla
                </button>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php") ?>

<script>
const BASE = '<?= RUTA_URL ?>';

function saveRule() {
    const idCliente = document.getElementById('ruleCliente').value;
    const idProvider = document.getElementById('ruleProvider').value;
    if (!idCliente || !idProvider) { alert('Selecciona un cliente y un proveedor'); return; }

    fetch(BASE + 'ajax/forwarding_rules.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'crear', id_cliente: idCliente, id_provider: idProvider })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) location.reload();
        else alert('Error: ' + data.message);
    })
    .catch(err => alert('Error: ' + err.message));
}

// Toggle switch handler
document.querySelectorAll('.toggle-rule').forEach(sw => {
    sw.addEventListener('change', function() {
        fetch(BASE + 'ajax/forwarding_rules.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'toggle', id: this.dataset.id, activo: this.checked ? 1 : 0 })
        })
        .then(r => r.json())
        .then(data => { if (!data.success) { this.checked = !this.checked; alert('Error'); } })
        .catch(() => { this.checked = !this.checked; });
    });
});

// Delete handler
document.querySelectorAll('.btn-delete-rule').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!confirm('¿Eliminar esta regla de forwarding?')) return;
        fetch(BASE + 'ajax/forwarding_rules.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'eliminar', id: this.dataset.id })
        })
        .then(r => r.json())
        .then(data => { if (data.success) location.reload(); else alert('Error: ' + data.message); })
        .catch(err => alert('Error: ' + err.message));
    });
});

$(document).ready(function() {
    if ($('#tblRules').length) {
        $('#tblRules').DataTable({
            responsive: true,
            language: { url: '//cdn.jsdelivr.net/npm/datatables.net-plugins@1.13.7/i18n/es-ES.json' },
            pageLength: 25,
            order: [[0, 'desc']]
        });
    }
});
</script>
