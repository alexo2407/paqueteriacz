<?php 
start_secure_session();
if(!isset($_SESSION['registrado'])) { header('location:'.RUTA_URL.'login'); die(); }
require_once __DIR__ . '/../../../utils/crm_roles.php';
$userId = (int)$_SESSION['idUsuario'];

// Si no es admin y no es cliente, fuera
if (!isUserAdmin($userId) && !isUserCliente($userId)) { header('Location: ' . RUTA_URL . 'dashboard'); exit; }

require_once __DIR__ . '/../../../controlador/crm.php';
$crmController = new CrmController();
// Obtener usuarios solo si es admin (optimización)
$usuarios = isUserAdmin($userId) ? $crmController->obtenerUsuarios() : [];

$id = isset($parametros[0]) ? (int)$parametros[0] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($id <= 0) {
    header('Location: ' . RUTA_URL . 'crm/listar');
    exit;
}

require_once __DIR__ . '/../../../modelo/crm_lead.php';
$lead = CrmLead::obtenerPorId($id);

if (!$lead) {
    header('Location: ' . RUTA_URL . 'crm/listar');
    exit;
}

// Validar Ownership
if (!isUserAdmin($userId)) {
    if (isUserCliente($userId) && $lead['cliente_id'] != $userId) {
         header('Location: ' . RUTA_URL . 'crm/listar');
         exit;
    }
}

include("vista/includes/header.php");
?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Editar Lead #<?= $id ?></h5>
                    <div>
                         <a href="<?= RUTA_URL ?>crm/ver/<?= $id ?>" class="btn btn-sm btn-info text-white me-2">
                            <i class="bi bi-eye"></i> Ver Detalle
                        </a>
                        <!-- Volver condicional -->
                        <?php $backLink = isUserCliente($userId) && !isUserAdmin($userId) ? RUTA_URL.'crm/notificaciones' : RUTA_URL.'crm/listar'; ?>
                        <a href="<?= $backLink ?>" class="btn btn-sm btn-light text-primary">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form action="<?= RUTA_URL ?>crm/guardarLead" method="POST">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <!-- Mantener proveedor original para updates -->
                        <input type="hidden" name="proveedor_id" value="<?= $lead['proveedor_id'] ?>">
                        <!-- Si es cliente, mantener su propio ID oculto -->
                        <?php if(!isUserAdmin($userId)): ?>
                            <input type="hidden" name="cliente_id" value="<?= $lead['cliente_id'] ?>">
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="fw-bold">Proveedor Lead ID:</label>
                                <input type="text" class="form-control-plaintext" value="<?= htmlspecialchars($lead['proveedor_lead_id']) ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="fw-bold">Estado Actual:</label>
                                <div><span class="badge bg-secondary"><?= $lead['estado_actual'] ?></span></div>
                                <small class="text-muted">Para cambiar estado use la opción "Cambiar Estado" en la vista de detalle.</small>
                            </div>
                        </div>
                        
                        <hr>
                        <h6 class="text-muted mb-3">Datos del Lead</h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre del Cliente Final</label>
                                <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($lead['nombre']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($lead['telefono']) ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Producto de Interés</label>
                                <input type="text" name="producto" class="form-control" value="<?= htmlspecialchars($lead['producto']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Precio Estimado</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" name="precio" class="form-control" step="0.01" min="0" value="<?= $lead['precio'] ?>">
                                </div>
                            </div>
                        </div>

                        <?php if(isUserAdmin($userId)): ?>
                        <hr>
                        <h6 class="text-muted mb-3">Asignación</h6>

                        <div class="mb-3">
                            <label class="form-label">Asignar a Cliente (Usuario del sistema)</label>
                            <select name="cliente_id" class="form-select">
                                <option value="">-- Sin asignar --</option>
                                <?php foreach ($usuarios as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $lead['cliente_id'] == $user['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['nombre']) ?> (<?= $user['email'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
