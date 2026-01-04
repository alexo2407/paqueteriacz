<?php 

start_secure_session();

if(!isset($_SESSION['registrado'])) {
    header('location:'.RUTA_URL.'login');
    die();
}

require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../utils/crm_roles.php';

// Verificar permisos: Admin o Cliente
$userId = (int)$_SESSION['idUsuario'];
if (!isUserAdmin($userId) && !isUserCliente($userId)) {
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

require_once __DIR__ . '/../../../controlador/crm.php';

$id = isset($parametros[0]) ? (int)$parametros[0] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if (!$id) {
    header('Location: ' . RUTA_URL . 'crm/listar');
    exit;
}

$crmController = new CrmController();
$datos = $crmController->ver($id);

if (!$datos) {
    header('Location: ' . RUTA_URL . 'crm/listar');
    exit;
}

$lead = $datos['lead'];
$timeline = $datos['timeline'];
$webhooks = $datos['webhooks'];

include("vista/includes/header.php");
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-file-earmark-text"></i> Detalle del Lead #<?= $lead['id'] ?></h2>
        <div>
            <button type="button" class="btn btn-warning me-2 text-dark" data-bs-toggle="modal" data-bs-target="#cambiarEstadoModal">
                <i class="bi bi-arrow-repeat"></i> Cambiar Estado
            </button>
            <a href="<?= RUTA_URL ?>crm/editar/<?= $lead['id'] ?>" class="btn btn-primary me-2">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="<?= RUTA_URL ?>crm/listar" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Información del Lead -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-person-badge"></i> Información del Lead</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Proveedor Lead ID:</strong> <code><?= htmlspecialchars($lead['proveedor_lead_id']) ?></code></p>
                            <p><strong>Nombre:</strong> <?= htmlspecialchars($lead['nombre'] ?? 'N/A') ?></p>
                            <p><strong>Teléfono:</strong> <?= htmlspecialchars($lead['telefono'] ?? 'N/A') ?></p>
                            <p><strong>Producto:</strong> <?= htmlspecialchars($lead['producto'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Precio:</strong> Q<?= number_format($lead['precio'] ?? 0, 2) ?></p>
                            <p><strong>Proveedor ID:</strong> <?= $lead['proveedor_id'] ?></p>
                            <p><strong>Cliente ID:</strong> <?= $lead['cliente_id'] ?? 'N/A' ?></p>
                            <p><strong>Fecha/Hora:</strong> <?= date('d/m/Y H:i', strtotime($lead['fecha_hora'])) ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Estado Actual:</strong> 
                                <?php
                                $badgeClass = match($lead['estado_actual']) {
                                    'EN_ESPERA' => 'warning',
                                    'APROBADO' => 'success',
                                    'CONFIRMADO' => 'primary',
                                    'EN_TRANSITO' => 'info',
                                    'EN_BODEGA' => 'secondary',
                                    'CANCELADO' => 'danger',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $badgeClass ?> fs-6"><?= $lead['estado_actual'] ?></span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Creado:</strong> <?= date('d/m/Y H:i:s', strtotime($lead['created_at'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historial de Estados</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($timeline)): ?>
                        <p class="text-muted">No hay cambios de estado registrados.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($timeline as $item): ?>
                            <div class="timeline-item mb-3 pb-3 border-bottom">
                                <div class="d-flex align-items-start">
                                    <div class="timeline-badge bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="bi bi-arrow-right"></i>
                                    </div>
                                    <div class="ms-3 flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="mb-1">Estado: <span class="badge bg-secondary"><?= $item['estado_nuevo'] ?></span></h6>
                                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?></small>
                                        </div>
                                        <div class="small">Por: <?= htmlspecialchars($item['actor_nombre'] ?? 'Sistema') ?></div>
                                        <?php if ($item['observaciones']): ?>
                                            <p class="mb-0 text-muted"><?= htmlspecialchars($item['observaciones']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Webhooks -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-send"></i> Webhooks</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($webhooks)): ?>
                        <p class="text-muted">No hay webhooks registrados.</p>
                    <?php else: ?>
                        <?php foreach ($webhooks as $webhook): ?>
                        <div class="webhook-item mb-3 p-2 border rounded">
                            <div class="d-flex justify-content-between">
                                <strong><?= $webhook['event_type'] ?></strong>
                                <span class="badge bg-<?= $webhook['status'] === 'sent' ? 'success' : ($webhook['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                    <?= $webhook['status'] ?>
                                </span>
                            </div>
                            <small class="text-muted d-block">Intentos: <?= $webhook['attempts'] ?></small>
                            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($webhook['created_at'])) ?></small>
                            <?php if ($webhook['last_error']): ?>
                                <small class="text-danger d-block mt-1">Error: <?= htmlspecialchars($webhook['last_error']) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cambiar Estado -->
<div class="modal fade" id="cambiarEstadoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= RUTA_URL ?>crm/cambiarEstado/<?= $lead['id'] ?>" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar Estado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nuevo Estado</label>
                        <select name="estado" class="form-select" required>
                            <?php 
                            $estados = ['EN_ESPERA','APROBADO','CONFIRMADO','EN_TRANSITO','EN_BODEGA','CANCELADO'];
                            foreach($estados as $est) {
                                $selected = $est == $lead['estado_actual'] ? 'selected' : '';
                                echo "<option value='$est' $selected>$est</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3" placeholder="Razón del cambio..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
