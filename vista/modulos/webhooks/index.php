<?php
$usaDataTables = true;
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../utils/csrf.php';
require_once __DIR__ . '/../../../modelo/webhook.php';
require_once __DIR__ . '/../../../modelo/usuario.php';

start_secure_session();
require_login();

if (!isSuperAdmin()) {
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

// ── Procesar acciones POST ──────────────────────────────────────────
$mensaje = null;
$tipoMensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    require_csrf_token($_POST['csrf_token'] ?? null);
    $accion = $_POST['accion'];

    try {
        switch ($accion) {
            case 'crear':
                WebhookModel::crear($_POST);
                $mensaje = 'Webhook creado correctamente.';
                $tipoMensaje = 'success';
                break;

            case 'editar':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    WebhookModel::actualizar($id, $_POST);
                    $mensaje = 'Webhook actualizado correctamente.';
                    $tipoMensaje = 'success';
                }
                break;

            case 'eliminar':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    WebhookModel::eliminar($id);
                    $mensaje = 'Webhook eliminado correctamente.';
                    $tipoMensaje = 'warning';
                }
                break;

            case 'toggle':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    WebhookModel::toggleActivo($id);
                    $mensaje = 'Estado actualizado.';
                    $tipoMensaje = 'info';
                }
                break;
        }
    } catch (Exception $e) {
        $mensaje = 'Error: ' . $e->getMessage();
        $tipoMensaje = 'danger';
    }

    // PRG: redirect para evitar reenvío de formulario
    if ($mensaje) {
        $_SESSION['flash_msg'] = $mensaje;
        $_SESSION['flash_type'] = $tipoMensaje;
        header('Location: ' . RUTA_URL . 'webhooks');
        exit;
    }
}

// Recuperar flash message
if (isset($_SESSION['flash_msg'])) {
    $mensaje = $_SESSION['flash_msg'];
    $tipoMensaje = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
}

// ── Obtener datos ──────────────────────────────────────────────────
$configs = WebhookModel::listarConfigs();
$logs = WebhookModel::obtenerLogs(100);

// Lista de clientes para el select
$um = new UsuarioModel();
$usuarios = $um->mostrarUsuarios();

// Webhook a editar (si viene por GET)
$editando = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $editando = WebhookModel::obtenerPorId((int)$_GET['editar']);
}
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-broadcast"></i> Webhooks</h2>
            <p class="text-muted mb-0">Configuración y monitoreo de notificaciones a APIs externas</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalFormWebhook" onclick="limpiarFormulario()">
                <i class="bi bi-plus-circle"></i> Nuevo Webhook
            </button>
            <a href="<?= RUTA_URL ?>dashboard" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show">
        <i class="bi bi-<?= $tipoMensaje === 'success' ? 'check-circle' : ($tipoMensaje === 'danger' ? 'x-circle' : 'info-circle') ?>"></i>
        <?= htmlspecialchars($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Tarjetas de configuración -->
    <div class="row mb-4">
        <?php if (empty($configs)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No hay webhooks configurados. Haz clic en <strong>"Nuevo Webhook"</strong> para agregar uno.
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($configs as $cfg): ?>
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card h-100 border-<?= $cfg['activo'] ? 'success' : 'secondary' ?>">
                <div class="card-header d-flex justify-content-between align-items-center bg-<?= $cfg['activo'] ? 'success' : 'secondary' ?> text-white">
                    <strong><i class="bi bi-broadcast"></i> <?= htmlspecialchars($cfg['nombre']) ?></strong>
                    <div class="d-flex gap-1">
                        <!-- Toggle activo -->
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="toggle">
                            <input type="hidden" name="id" value="<?= $cfg['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-<?= $cfg['activo'] ? 'light' : 'warning' ?>" title="<?= $cfg['activo'] ? 'Desactivar' : 'Activar' ?>">
                                <i class="bi bi-<?= $cfg['activo'] ? 'pause-circle' : 'play-circle' ?>"></i>
                            </button>
                        </form>
                        <!-- Editar -->
                        <button class="btn btn-sm btn-light" onclick="editarWebhook(<?= htmlspecialchars(json_encode($cfg)) ?>)" title="Editar">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <!-- Eliminar -->
                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este webhook y todos sus logs?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $cfg['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted">Cliente:</small><br>
                        <strong><?= htmlspecialchars($cfg['cliente_nombre'] ?? 'ID: ' . $cfg['id_cliente']) ?></strong>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">URL Login:</small><br>
                        <code class="small text-break"><?= htmlspecialchars($cfg['url_login']) ?></code>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">URL Webhook:</small><br>
                        <code class="small text-break"><?= htmlspecialchars($cfg['url_webhook']) ?></code>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-2">
                            <small class="text-muted">Auth User:</small><br>
                            <code><?= htmlspecialchars($cfg['auth_user']) ?></code>
                        </div>
                        <div class="col-6 mb-2">
                            <small class="text-muted">CustomersId:</small><br>
                            <code><?= $cfg['customers_id'] ?? 'N/A' ?></code>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> <?= $cfg['total_ok'] ?> OK</span>
                        <span class="badge bg-danger"><i class="bi bi-x-circle"></i> <?= $cfg['total_error'] ?> Errores</span>
                        <span class="badge bg-primary"><i class="bi bi-list"></i> <?= $cfg['total_logs'] ?> Total</span>
                    </div>
                </div>
                <div class="card-footer text-muted small">
                    Creado: <?= date('d/m/Y H:i', strtotime($cfg['created_at'])) ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Log de actividad -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-journal-text"></i> Log de Webhooks</h5>
            <span class="badge bg-light text-primary"><?= count($logs) ?> registros</span>
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
            <div class="text-center py-4 text-muted">
                <i class="bi bi-inbox display-4 d-block mb-2 opacity-25"></i>
                <p>Aún no se han enviado webhooks.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table id="tablaWebhooks" class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Pedido</th>
                            <th>Estado Enviado</th>
                            <th>Resultado</th>
                            <th>HTTP</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><small><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></small></td>
                            <td><strong><?= htmlspecialchars($log['cliente_nombre'] ?? 'N/A') ?></strong></td>
                            <td>
                                <a href="<?= RUTA_URL ?>logistica/ver/<?= $log['id_pedido'] ?>" class="text-decoration-none">
                                    #<?= htmlspecialchars($log['numero_orden']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($log['estado_enviado']) ?></td>
                            <td>
                                <?php
                                $statusBadge = match($log['status']) {
                                    'ok' => 'bg-success', 'error' => 'bg-danger',
                                    'pending' => 'bg-warning', default => 'bg-secondary'
                                };
                                $statusIcon = match($log['status']) {
                                    'ok' => 'bi-check-circle', 'error' => 'bi-x-circle',
                                    'pending' => 'bi-clock', default => 'bi-question-circle'
                                };
                                ?>
                                <span class="badge <?= $statusBadge ?>">
                                    <i class="bi <?= $statusIcon ?>"></i> <?= strtoupper($log['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($log['response_code']): ?>
                                <code class="<?= $log['response_code'] >= 200 && $log['response_code'] < 300 ? 'text-success' : 'text-danger' ?>">
                                    <?= $log['response_code'] ?>
                                </code>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" type="button"
                                        data-bs-toggle="modal" data-bs-target="#modalLog<?= $log['id'] ?>">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>

                        <!-- Modal detalles log -->
                        <div class="modal fade" id="modalLog<?= $log['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header bg-<?= $log['status'] === 'ok' ? 'success' : 'danger' ?> text-white">
                                        <h5 class="modal-title">
                                            <i class="bi bi-broadcast"></i>
                                            Webhook #<?= $log['id'] ?> — Pedido #<?= htmlspecialchars($log['numero_orden']) ?>
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Request</h6>
                                                <pre class="bg-dark text-light p-3 rounded" style="max-height:300px;overflow-y:auto;font-size:.8rem"><?= htmlspecialchars(json_encode(json_decode($log['request_body'] ?? '{}'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Response (HTTP <?= $log['response_code'] ?? 'N/A' ?>)</h6>
                                                <pre class="bg-dark text-light p-3 rounded" style="max-height:300px;overflow-y:auto;font-size:.8rem"><?= htmlspecialchars(json_encode(json_decode($log['response_body'] ?? '{}'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                            </div>
                                        </div>
                                        <?php if ($log['error_message']): ?>
                                        <div class="alert alert-danger mt-3 mb-0">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            <strong>Error:</strong> <?= htmlspecialchars($log['error_message']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer text-muted small">
                                        Intentos: <?= $log['intentos'] ?> |
                                        Enviado: <?= $log['enviado_at'] ? date('d/m/Y H:i:s', strtotime($log['enviado_at'])) : 'N/A' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ══════ Modal Crear/Editar Webhook ══════ -->
<div class="modal fade" id="modalFormWebhook" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formWebhook">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" id="formAccion" value="crear">
                <input type="hidden" name="id" id="formId" value="">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalTitulo">
                        <i class="bi bi-plus-circle"></i> Nuevo Webhook
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="fNombre" class="form-control" required
                                   placeholder="Ej: LogisPro Guatemala">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Cliente <span class="text-danger">*</span></label>
                            <select name="id_cliente" id="fIdCliente" class="form-select" required>
                                <option value="">Seleccionar cliente...</option>
                                <?php foreach ($usuarios as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?> (ID: <?= $u['id'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">URL Login (Autenticación) <span class="text-danger">*</span></label>
                            <input type="url" name="url_login" id="fUrlLogin" class="form-control" required
                                   placeholder="https://api.ejemplo.com/login">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">URL Webhook (Envío de estado) <span class="text-danger">*</span></label>
                            <input type="url" name="url_webhook" id="fUrlWebhook" class="form-control" required
                                   placeholder="https://api.ejemplo.com/orders/update">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Auth User <span class="text-danger">*</span></label>
                            <input type="text" name="auth_user" id="fAuthUser" class="form-control" required
                                   placeholder="usuario.api">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Auth Password <span class="text-danger">*</span></label>
                            <input type="text" name="auth_password" id="fAuthPassword" class="form-control" required
                                   placeholder="contraseña">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">CustomersId</label>
                            <input type="number" name="customers_id" id="fCustomersId" class="form-control"
                                   placeholder="ID externo del cliente">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="activo" id="fActivo" checked>
                                <label class="form-check-label" for="fActivo">Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmit">
                        <i class="bi bi-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
function limpiarFormulario() {
    document.getElementById('formAccion').value = 'crear';
    document.getElementById('formId').value = '';
    document.getElementById('modalTitulo').innerHTML = '<i class="bi bi-plus-circle"></i> Nuevo Webhook';
    document.getElementById('fNombre').value = '';
    document.getElementById('fIdCliente').value = '';
    document.getElementById('fUrlLogin').value = '';
    document.getElementById('fUrlWebhook').value = '';
    document.getElementById('fAuthUser').value = '';
    document.getElementById('fAuthPassword').value = '';
    document.getElementById('fCustomersId').value = '';
    document.getElementById('fActivo').checked = true;
}

function editarWebhook(cfg) {
    document.getElementById('formAccion').value = 'editar';
    document.getElementById('formId').value = cfg.id;
    document.getElementById('modalTitulo').innerHTML = '<i class="bi bi-pencil"></i> Editar Webhook';
    document.getElementById('fNombre').value = cfg.nombre;
    document.getElementById('fIdCliente').value = cfg.id_cliente;
    document.getElementById('fUrlLogin').value = cfg.url_login;
    document.getElementById('fUrlWebhook').value = cfg.url_webhook;
    document.getElementById('fAuthUser').value = cfg.auth_user;
    document.getElementById('fAuthPassword').value = cfg.auth_password;
    document.getElementById('fCustomersId').value = cfg.customers_id || '';
    document.getElementById('fActivo').checked = cfg.activo == 1;

    new bootstrap.Modal(document.getElementById('modalFormWebhook')).show();
}

$(document).ready(function() {
    if ($.fn.DataTable && document.getElementById('tablaWebhooks')) {
        $('#tablaWebhooks').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            responsive: true
        });
    }
});
</script>
