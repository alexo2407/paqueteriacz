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
.provider-status { font-size: 0.8rem; padding: 0.3em 0.75em; border-radius: 8px; font-weight: 600; }
.provider-status.active { background: #d1fae5; color: #065f46; }
.provider-status.inactive { background: #fee2e2; color: #991b1b; }
.btn-test { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; border: none; }
.btn-test:hover { background: linear-gradient(135deg, #5a6fd6, #6a4199); color: #fff; transform: translateY(-1px); }
.test-result { border-radius: 10px; padding: 1rem; margin-top: 1rem; display: none; }
.test-result.success { background: #d1fae5; border: 1px solid #6ee7b7; }
.test-result.error { background: #fee2e2; border: 1px solid #fca5a5; }
</style>

<div class="container-fluid py-3">
    <div class="card fwd-card mb-4">
        <div class="fwd-header">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-building fs-3"></i>
                        </div>
                        <div>
                            <h3>Proveedores Externos</h3>
                            <p class="mb-0 opacity-75">Configura las conexiones API con proveedores de logística</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end gap-2 mt-3 mt-md-0">
                        <a href="<?= RUTA_URL ?>forwarding" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.4);border-radius:10px;">
                            <i class="bi bi-arrow-left me-1"></i> Dashboard
                        </a>
                        <button class="btn" style="background:#fff;color:#667eea;border:none;border-radius:10px;font-weight:600;" data-bs-toggle="modal" data-bs-target="#modalProvider">
                            <i class="bi bi-plus-circle-fill me-1"></i> Nuevo Proveedor
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="tblProviders" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Slug</th>
                            <th>URL Base</th>
                            <th>Auth</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($proveedores as $p): ?>
                        <tr data-id="<?= $p['id'] ?>">
                            <td><span class="badge bg-light text-dark border">#<?= $p['id'] ?></span></td>
                            <td class="fw-bold"><?= htmlspecialchars($p['nombre']) ?></td>
                            <td><code><?= htmlspecialchars($p['slug']) ?></code></td>
                            <td><small class="text-muted"><?= htmlspecialchars($p['base_url']) ?></small></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($p['auth_method']) ?></span></td>
                            <td>
                                <span class="provider-status <?= $p['activo'] ? 'active' : 'inactive' ?>">
                                    <?= $p['activo'] ? '● Activo' : '● Inactivo' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <button class="btn btn-sm btn-outline-primary btn-edit-provider" title="Editar"
                                            data-id="<?= $p['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                                            data-slug="<?= htmlspecialchars($p['slug']) ?>"
                                            data-baseurl="<?= htmlspecialchars($p['base_url']) ?>"
                                            data-authep="<?= htmlspecialchars($p['auth_endpoint']) ?>"
                                            data-orderep="<?= htmlspecialchars($p['order_endpoint']) ?>"
                                            data-authmethod="<?= htmlspecialchars($p['auth_method']) ?>"
                                            data-webhooksecret="<?= htmlspecialchars(json_decode($p['credentials'], true)['webhook_secret'] ?? '') ?>"
                                            style="border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-test btn-test-provider" title="Test Conexión"
                                            data-id="<?= $p['id'] ?>"
                                            data-baseurl="<?= htmlspecialchars($p['base_url']) ?>"
                                            data-authep="<?= htmlspecialchars($p['auth_endpoint']) ?>"
                                            data-slug="<?= htmlspecialchars($p['slug']) ?>"
                                            style="border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-plug"></i>
                                    </button>
                                    <button class="btn btn-sm btn-toggle-provider <?= $p['activo'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                            title="<?= $p['activo'] ? 'Desactivar' : 'Activar' ?>"
                                            data-id="<?= $p['id'] ?>"
                                            data-activo="<?= $p['activo'] ? 0 : 1 ?>"
                                            style="border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-<?= $p['activo'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear/Editar Proveedor -->
<div class="modal fade" id="modalProvider" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border:none;border-radius:16px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border-radius:16px 16px 0 0;">
                <h5 class="modal-title" id="modalProviderTitle"><i class="bi bi-building me-2"></i>Nuevo Proveedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="providerId" value="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nombre</label>
                        <input type="text" class="form-control" id="provNombre" placeholder="LogisPro México">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Slug <small class="text-muted">(identificador único)</small></label>
                        <input type="text" class="form-control" id="provSlug" placeholder="logispro">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">URL Base de la API</label>
                        <input type="url" class="form-control" id="provBaseUrl" placeholder="https://apigateway.logispro.app">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Endpoint de Auth</label>
                        <input type="text" class="form-control" id="provAuthEp" value="/api/AccountApi">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Endpoint de Órdenes</label>
                        <input type="text" class="form-control" id="provOrderEp" value="/api/Orders/OrderAndOrderDetail">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Método de Auth</label>
                        <select class="form-select" id="provAuthMethod">
                            <option value="bearer_jwt" selected>Bearer JWT</option>
                            <option value="api_key">API Key</option>
                            <option value="basic">Basic Auth</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Usuario API</label>
                        <input type="text" class="form-control" id="provUserName" placeholder="usuario.api">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Contraseña API</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="provPassword" placeholder="••••••••">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass()">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-warning"><i class="bi bi-key-fill me-1"></i>Webhook Secret</label>
                        <input type="text" class="form-control" id="provWebhookSecret" placeholder="(Se genera auto si está vacío)">
                        <input type="hidden" id="provExistingWebhookSecret" value="">
                    </div>
                </div>

                <!-- Test Connection -->
                <div class="mt-3">
                    <button class="btn btn-test w-100" id="btnTestModal" onclick="testConnection()">
                        <i class="bi bi-plug me-2"></i>Probar Conexión
                    </button>
                    <div id="testResultModal" class="test-result"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSaveProvider" onclick="saveProvider()">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php") ?>

<script>
const BASE = '<?= RUTA_URL ?>';

function togglePass() {
    const inp = document.getElementById('provPassword');
    const ico = document.getElementById('eyeIcon');
    if (inp.type === 'password') { inp.type = 'text'; ico.className = 'bi bi-eye-slash'; }
    else { inp.type = 'password'; ico.className = 'bi bi-eye'; }
}

function testConnection() {
    const btn = document.getElementById('btnTestModal');
    const res = document.getElementById('testResultModal');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon me-2"></i>Conectando...';
    res.style.display = 'none';

    fetch(BASE + 'ajax/forwarding_providers.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({
            action: 'test',
            slug: document.getElementById('provSlug').value || 'logispro',
            base_url: document.getElementById('provBaseUrl').value,
            auth_endpoint: document.getElementById('provAuthEp').value,
            userName: document.getElementById('provUserName').value,
            password: document.getElementById('provPassword').value,
        })
    })
    .then(r => r.json())
    .then(data => {
        res.style.display = 'block';
        if (data.success) {
            res.className = 'test-result success';
            res.innerHTML = `<i class="bi bi-check-circle-fill text-success me-2"></i><strong>Conexión exitosa</strong>
                <div class="mt-2"><strong>CustomersId:</strong> <span class="badge bg-primary">${data.customersId}</span></div>
                <div><strong>Token:</strong> <code>${data.token_preview || ''}</code></div>`;
        } else {
            res.className = 'test-result error';
            res.innerHTML = `<i class="bi bi-x-circle-fill text-danger me-2"></i><strong>Error:</strong> ${data.message}`;
        }
    })
    .catch(err => {
        res.style.display = 'block';
        res.className = 'test-result error';
        res.innerHTML = `<i class="bi bi-x-circle-fill text-danger me-2"></i>Error de red: ${err.message}`;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plug me-2"></i>Probar Conexión';
    });
}

function saveProvider() {
    const id = document.getElementById('providerId').value;
    const action = id ? 'actualizar' : 'crear';
    const body = {
        action,
        id: id || undefined,
        nombre: document.getElementById('provNombre').value,
        slug: document.getElementById('provSlug').value,
        base_url: document.getElementById('provBaseUrl').value,
        auth_endpoint: document.getElementById('provAuthEp').value,
        order_endpoint: document.getElementById('provOrderEp').value,
        auth_method: document.getElementById('provAuthMethod').value,
        userName: document.getElementById('provUserName').value,
        password: document.getElementById('provPassword').value,
        webhook_secret: document.getElementById('provWebhookSecret').value,
        existing_webhook_secret: document.getElementById('provExistingWebhookSecret').value,
    };

    fetch(BASE + 'ajax/forwarding_providers.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ icon: 'success', title: action === 'crear' ? '¡Proveedor creado!' : '¡Proveedor actualizado!', timer: 1400, showConfirmButton: false })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error al guardar', text: data.message || 'No se pudo guardar el proveedor.' });
        }
    })
    .catch(err => Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message }));
}

// Edit button handler
document.querySelectorAll('.btn-edit-provider').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('providerId').value = this.dataset.id;
        document.getElementById('provNombre').value = this.dataset.nombre;
        document.getElementById('provSlug').value = this.dataset.slug;
        document.getElementById('provBaseUrl').value = this.dataset.baseurl;
        document.getElementById('provAuthEp').value = this.dataset.authep;
        document.getElementById('provOrderEp').value = this.dataset.orderep;
        document.getElementById('provAuthMethod').value = this.dataset.authmethod;
        document.getElementById('provUserName').value = '';
        document.getElementById('provPassword').value = '';
        document.getElementById('provWebhookSecret').value = this.dataset.webhooksecret;
        document.getElementById('provExistingWebhookSecret').value = this.dataset.webhooksecret;
        document.getElementById('modalProviderTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Editar Proveedor';
        document.getElementById('testResultModal').style.display = 'none';
        new bootstrap.Modal(document.getElementById('modalProvider')).show();
    });
});

// Toggle button handler
document.querySelectorAll('.btn-toggle-provider').forEach(btn => {
    btn.addEventListener('click', function() {
        const nuevoActivo = this.dataset.activo;
        const accion = nuevoActivo == 1 ? 'activar' : 'desactivar';
        const id = this.dataset.id;
        Swal.fire({
            title: `¿${accion.charAt(0).toUpperCase()+accion.slice(1)} proveedor?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;
            fetch(BASE + 'ajax/forwarding_providers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'toggle', id, activo: nuevoActivo })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'No se pudo cambiar el estado.' });
            })
            .catch(err => Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message }));
        });
    });
});

// Test from table — usa Swal con inputs en lugar de prompt() nativo
document.querySelectorAll('.btn-test-provider').forEach(btn => {
    btn.addEventListener('click', function() {
        const self = this;
        const slug    = this.dataset.slug;
        const baseUrl = this.dataset.baseurl;
        const authEp  = this.dataset.authep;

        Swal.fire({
            title: 'Credenciales para test',
            html:
                '<input id="swal-user" class="swal2-input" placeholder="Usuario API">' +
                '<input id="swal-pass" type="password" class="swal2-input" placeholder="Contraseña API">',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Probar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const u = document.getElementById('swal-user').value;
                const p = document.getElementById('swal-pass').value;
                if (!u || !p) { Swal.showValidationMessage('Completa ambos campos'); return false; }
                return { userName: u, password: p };
            }
        }).then(result => {
            if (!result.isConfirmed) return;
            self.innerHTML = '<i class="bi bi-arrow-repeat spin-icon"></i>';
            self.disabled = true;

            fetch(BASE + 'ajax/forwarding_providers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'test', slug, base_url: baseUrl, auth_endpoint: authEp, ...result.value })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Conexión exitosa',
                        html: `<p><strong>CustomersId:</strong> <span class="badge bg-primary fs-6">${data.customersId}</span></p>
                               <p><strong>Token:</strong> <code>${data.token_preview || ''}</code></p>`
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Fallo de conexión', text: data.message });
                }
            })
            .catch(err => Swal.fire({ icon: 'error', title: 'Error de red', text: err.message }))
            .finally(() => { self.innerHTML = '<i class="bi bi-plug"></i>'; self.disabled = false; });
        });
    });
});

// Reset modal on open for "new"
document.getElementById('modalProvider').addEventListener('show.bs.modal', function(e) {
    if (e.relatedTarget && !e.relatedTarget.classList.contains('btn-edit-provider')) {
        document.getElementById('providerId').value = '';
        document.getElementById('provNombre').value = '';
        document.getElementById('provSlug').value = '';
        document.getElementById('provBaseUrl').value = '';
        document.getElementById('provAuthEp').value = '/api/AccountApi';
        document.getElementById('provOrderEp').value = '/api/Orders/OrderAndOrderDetail';
        document.getElementById('provAuthMethod').value = 'bearer_jwt';
        document.getElementById('provUserName').value = '';
        document.getElementById('provPassword').value = '';
        document.getElementById('provWebhookSecret').value = '';
        document.getElementById('provExistingWebhookSecret').value = '';
        document.getElementById('modalProviderTitle').innerHTML = '<i class="bi bi-building me-2"></i>Nuevo Proveedor';
        document.getElementById('testResultModal').style.display = 'none';
    }
});

// DataTable init
$(document).ready(function() {
    if ($('#tblProviders').length) {
        $('#tblProviders').DataTable({
            responsive: true,
            language: { url: '//cdn.jsdelivr.net/npm/datatables.net-plugins@1.13.7/i18n/es-ES.json' },
            pageLength: 25,
            order: [[0, 'desc']]
        });
    }
});
</script>
