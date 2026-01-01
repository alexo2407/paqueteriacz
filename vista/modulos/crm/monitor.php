<?php 
start_secure_session();
if(!isset($_SESSION['registrado'])) { header('location:'.RUTA_URL.'login'); die(); }
require_once __DIR__ . '/../../../utils/permissions.php';
if (!isAdmin()) { header('Location: ' . RUTA_URL . 'dashboard'); exit; }

require_once __DIR__ . '/../../../controlador/crm.php';
$crmController = new CrmController();
$datos = $crmController->monitor();
$inboxPending = $datos['inbox']['pendientes'] ?? [];
$outboxFailed = $datos['outbox']['fallidos'] ?? [];

include("vista/includes/header.php");
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-activity"></i> Monitor del Worker</h2>
        <a href="<?= RUTA_URL ?>crm/dashboard" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <!-- Estadísticas -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h3><?= $datos['stats']['inbox_pending'] ?></h3>
                    <p class="mb-0">Inbox Pendientes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?= $datos['stats']['inbox_processed'] ?></h3>
                    <p class="mb-0">Inbox Procesados</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3><?= $datos['stats']['outbox_pending'] ?></h3>
                    <p class="mb-0">Outbox Pendientes</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h3><?= $datos['stats']['outbox_failed'] ?></h3>
                    <p class="mb-0">Webhooks Fallidos</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Colas -->
    <div class="row">
        <div class="col-lg-6">
            <!-- Inbox Pendiente -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Inbox Pendiente/Fallido</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fuente</th>
                                <th>Estado</th>
                                <th>Recibido</th>
                                <th>Error</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inboxPending as $msg): ?>
                            <tr>
                                <td><?= $msg['id'] ?></td>
                                <td><?= $msg['source'] ?? 'N/A' ?></td>
                                <td><span class="badge bg-<?= ($msg['status'] ?? '') == 'failed' ? 'danger' : 'warning' ?>"><?= $msg['status'] ?? 'N/A' ?></span></td>
                                <td><?= $msg['received_at'] ?? 'N/A' ?></td>
                                <td class="text-danger small"><?= substr($msg['last_error'] ?? '', 0, 50) ?>...</td>
                                <td>
                                    <?php if(($msg['status'] ?? '') === 'failed'): ?>
                                    <form action="<?= RUTA_URL ?>crm/reintentarInbox/<?= $msg['id'] ?>" method="POST" class="d-inline">
                                        <button class="btn btn-sm btn-outline-primary" title="Reintentar"><i class="bi bi-arrow-clockwise"></i></button>
                                    </form>
                                    <?php endif; ?>
                                    <form action="<?= RUTA_URL ?>crm/eliminarInbox/<?= $msg['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('Eliminar mensaje?');">
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Outbox Fallido -->
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Outbox Fallido</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tipo</th>
                                <th>Lead ID</th>
                                <th>Intentos</th>
                                <th>Error</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outboxFailed as $msg): ?>
                            <tr>
                                <td><?= $msg['id'] ?></td>
                                <td><?= $msg['event_type'] ?? 'N/A' ?></td>
                                <td><?= $msg['lead_id'] ?? 'N/A' ?></td>
                                <td><?= $msg['attempts'] ?? 'N/A' ?></td>
                                <td class="text-danger small"><?= substr($msg['last_error'] ?? '', 0, 50) ?>...</td>
                                <td>
                                    <form action="<?= RUTA_URL ?>crm/reintentarOutbox/<?= $msg['id'] ?>" method="POST" class="d-inline">
                                        <button class="btn btn-sm btn-outline-primary" title="Reintentar"><i class="bi bi-arrow-clockwise"></i></button>
                                    </form>
                                    <form action="<?= RUTA_URL ?>crm/eliminarOutbox/<?= $msg['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('Eliminar mensaje?');">
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
