<?php 
start_secure_session();
if(!isset($_SESSION['registrado'])) { header('location:'.RUTA_URL.'login'); die(); }
require_once __DIR__ . '/../../../utils/permissions.php';
if (!isAdmin()) { header('Location: ' . RUTA_URL . 'dashboard'); exit; }

require_once __DIR__ . '/../../../controlador/crm.php';
$crmController = new CrmController();
$usuarios = $crmController->obtenerUsuarios();

// Generar secret aleatorio por defecto
$defaultSecret = bin2hex(random_bytes(16));

include("vista/includes/header.php");
?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-plus-lg"></i> Nueva Integración</h5>
                    <a href="<?= RUTA_URL ?>crm/integraciones" class="btn btn-sm btn-light text-primary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
                <div class="card-body">
                    <form action="<?= RUTA_URL ?>crm/guardarIntegracion" method="POST">
                        
                        <div class="mb-3">
                            <label class="form-label">Usuario <span class="text-danger">*</span></label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Seleccione un usuario...</option>
                                <?php foreach ($usuarios as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['nombre']) ?> (<?= $user['email'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Usuario asociado a esta integración.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo de Integración <span class="text-danger">*</span></label>
                            <select name="kind" class="form-select" required>
                                <option value="">Seleccione el tipo...</option>
                                <option value="cliente">Cliente (Recibe actualizaciones de sus pedidos)</option>
                                <option value="proveedor">Proveedor (Recibe actualizaciones de envíos)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Webhook URL <span class="text-danger">*</span></label>
                            <input type="url" name="webhook_url" class="form-control" placeholder="https://api.ejemplo.com/webhook" required>
                            <div class="form-text">URL donde se enviarán los eventos POST.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Secret Key <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="secret" id="secretField" class="form-control" value="<?= $defaultSecret ?>" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('secretField').value = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);">
                                    <i class="bi bi-arrow-clockwise"></i> Generar
                                </button>
                            </div>
                            <div class="form-text">Clave secreta utilizada para firmar los payloads (HMAC).</div>
                        </div>

                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" checked>
                            <label class="form-check-label" for="isActive">Activar Integración</label>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Guardar Integración
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
