<?php 
start_secure_session();
if(!isset($_SESSION['registrado'])) { header('location:'.RUTA_URL.'login'); die(); }
require_once __DIR__ . '/../../../utils/permissions.php';
if (!isAdmin()) { header('Location: ' . RUTA_URL . 'dashboard'); exit; }

require_once __DIR__ . '/../../../controlador/crm.php';
$crmController = new CrmController();
$usuarios = $crmController->obtenerUsuarios(); // Para seleccionar proveedor/cliente

include("vista/includes/header_materialize.php");
?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-person-plus"></i> Nuevo Lead Manual</h5>
                    <a href="<?= RUTA_URL ?>crm/listar" class="btn btn-sm btn-light text-primary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
                <div class="card-body">
                    <form action="<?= RUTA_URL ?>crm/guardarLead" method="POST">
                        
                        <h6 class="text-muted mb-3">Información del Proveedor</h6>
                        <div class="mb-3">
                            <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                            <select name="proveedor_id" class="form-select" required>
                                <option value="">Seleccione un proveedor...</option>
                                <?php foreach ($usuarios as $user): ?>
                                    <!-- Idealmente filtrar por rol de proveedor si es posible, pero todos usuarios sirve -->
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['nombre']) ?> (<?= $user['email'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Usuario que "envía" el lead.</div>
                        </div>

                        <hr>
                        <h6 class="text-muted mb-3">Datos del Lead</h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre del Cliente Final</label>
                                <input type="text" name="nombre" class="form-control" placeholder="Ej: Juan Pérez">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="telefono" class="form-control" placeholder="Ej: +502 1234 5678">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Producto de Interés</label>
                                <input type="text" name="producto" class="form-control" placeholder="Ej: Televisor 50 pulgadas">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Precio Estimado</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" name="precio" class="form-control" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-muted mb-3">Asignación (Opcional)</h6>

                        <div class="mb-3">
                            <label class="form-label">Asignar a Cliente (Usuario del sistema)</label>
                            <select name="cliente_id" class="form-select">
                                <option value="">-- Sin asignar --</option>
                                <?php foreach ($usuarios as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['nombre']) ?> (<?= $user['email'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Si el lead ya corresponde a un cliente registrado en el sistema.</div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Crear Lead
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>
