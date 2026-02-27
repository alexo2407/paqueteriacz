<?php 
start_secure_session();
if(!isset($_SESSION['registrado'])) { header('location:'.RUTA_URL.'login'); die(); }
require_once __DIR__ . '/../../../utils/permissions.php';
if (!isAdmin()) { header('Location: ' . RUTA_URL . 'dashboard'); exit; }

require_once __DIR__ . '/../../../controlador/crm.php';
$crmController = new CrmController();
$datos = $crmController->integraciones();
$integraciones = $datos['integraciones'];

include("vista/includes/header_materialize.php");
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-plug"></i> Integraciones y Webhooks</h2>
        <div>
            <a href="<?= RUTA_URL ?>crm/integraciones_crear" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Nueva Integración
            </a>
            <a href="<?= RUTA_URL ?>crm/dashboard" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Tipo</th>
                        <th>Webhook URL</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($integraciones)): ?>
                        <tr><td colspan="6" class="text-center text-muted">No hay integraciones configuradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($integraciones as $int): ?>
                        <tr>
                            <td><?= $int['id'] ?></td>
                            <td><?= htmlspecialchars($int['user_name'] ?? 'Usuario ' . $int['user_id']) ?></td>
                            <td><span class="badge bg-info"><?= $int['kind'] ?></span></td>
                            <td><code><?= htmlspecialchars($int['webhook_url']) ?></code></td>
                            <td>
                                <span class="badge bg-<?= $int['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $int['is_active'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= RUTA_URL ?>crm/integraciones_editar/<?= $int['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="<?= RUTA_URL ?>crm/eliminarIntegracion/<?= $int['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar esta integración?');">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>
