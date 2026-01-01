<?php 
start_secure_session();
if(!isset($_SESSION['registrado'])) { header('location:'.RUTA_URL.'login'); die(); }
require_once __DIR__ . '/../../../utils/permissions.php';
if (!isAdmin()) { header('Location: ' . RUTA_URL . 'dashboard'); exit; }

require_once __DIR__ . '/../../../controlador/crm.php';
$crmController = new CrmController();

// Obtener ID desde parámetros de ruta o GET (fallback)
$id = isset($parametros[0]) ? (int)$parametros[0] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($id <= 0) {
    header('Location: ' . RUTA_URL . 'crm/integraciones');
    exit;
}

$integracion = $crmController->obtenerIntegracion($id);
if (!$integracion) {
    header('Location: ' . RUTA_URL . 'crm/integraciones');
    exit;
}

$usuarios = $crmController->obtenerUsuarios();

include("vista/includes/header.php");
?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-pencil"></i> Editar Integración #<?= $id ?></h5>
                    <a href="<?= RUTA_URL ?>crm/integraciones" class="btn btn-sm btn-light text-primary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
                <div class="card-body">
                    <form action="<?= RUTA_URL ?>crm/guardarIntegracion" method="POST">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <!-- Nota: El modelo actual actualiza por user_id + kind. 
                             Si cambiamos usuarios/tipo, creará una nueva si no existe o actualizará la otra.
                             Para edición segura, lo ideal sería que el controlador maneje update por ID.
                             Pero revisamos CrmIntegration::guardar y usa user_id+kind para buscar.
                             Si queremos editar los campos de ESTA integración id, debemos mantener user/kind o
                             asegurar que el modelo use ID si se pasa.
                             
                             Dado que modificamos CrmIntegrationModel para agregar obtenerPorId y eliminar,
                             pero NO modificamos guardar() para usar ID en el WHERE.
                             
                             CRITICAL: Si cambiamos el usuario o tipo aqui, `guardar` puede crear una nueva o sobreescribir otra.
                             Vamos a deshabilitar el cambio de usuario y tipo en edición para evitar conflictos,
                             o mostrar advertencia. Lo más seguro es que sean readonly.
                        -->
                        
                        <input type="hidden" name="user_id" value="<?= $integracion['user_id'] ?>">
                        <input type="hidden" name="kind" value="<?= $integracion['kind'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($integracion['user_name'] ?? 'ID: ' . $integracion['user_id']) ?>" disabled>
                            <div class="form-text">El usuario no se puede cambiar en edición.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo de Integración</label>
                            <input type="text" class="form-control" value="<?= ucfirst($integracion['kind']) ?>" disabled>
                            <div class="form-text">El tipo no se puede cambiar en edición.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Webhook URL <span class="text-danger">*</span></label>
                            <input type="url" name="webhook_url" class="form-control" value="<?= htmlspecialchars($integracion['webhook_url']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Secret Key <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" name="secret" id="secretField" class="form-control" value="<?= htmlspecialchars($integracion['secret']) ?>" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('secretField').value = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);">
                                    <i class="bi bi-arrow-clockwise"></i> Regenerar
                                </button>
                            </div>
                        </div>

                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" <?= $integracion['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isActive">Activar Integración</label>
                        </div>

                        <div class="d-grid gap-2">
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
