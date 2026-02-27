<?php include("vista/includes/header_materialize.php"); ?>
<?php 
require_once __DIR__ . '/../../../controlador/departamento.php';
require_once __DIR__ . '/../../../controlador/pais.php';
$ctrl = new DepartamentosController();
$paisCtrl = new PaisesController();
$paises = $paisCtrl->listar();

$id = null;
if (isset($parametros) && isset($parametros[0])) {
    $id = (int)$parametros[0];
} else {
    $parts = explode('/', $_GET['enlace'] ?? '');
    $id = isset($parts[2]) ? (int)$parts[2] : null;
}
$d = $ctrl->ver($id);
?>

<style>
.location-header {
    background: linear-gradient(135deg, #005C97 0%, #363795 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px 12px 0 0;
}
.form-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    overflow: hidden;
}
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <?php if (!$d): ?>
                <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div>
                        <strong>Error:</strong> El departamento solicitado no existe.
                        <a href="<?= RUTA_URL ?>departamentos/listar" class="alert-link">Volver al listado</a>
                    </div>
                </div>
            <?php else: ?>
                <form method="post" action="<?= RUTA_URL ?>departamentos/actualizar/<?= urlencode($d['id']) ?>" class="card form-card">
                    <?php 
                    require_once __DIR__ . '/../../../utils/csrf.php';
                    echo csrf_field(); 
                    ?>
                    
                    <div class="location-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i> Editar Departamento</h3>
                            <p class="mb-0 opacity-75 small">Modificar datos de: <?= htmlspecialchars($d['nombre']) ?></p>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label class="form-label fw-bold">País Perteneciente <span class="text-danger">*</span></label>
                            <select name="id_pais" class="form-select select2" required style="width: 100%;">
                                <option value="">-- Seleccione un País --</option>
                                <?php foreach ($paises as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $p['id']==$d['id_pais']? 'selected':'' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Cambiar el país reasignará el departamento a otra región nacional.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Nombre del Departamento <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light"><i class="bi bi-geo"></i></span>
                                <input class="form-control" name="nombre" value="<?= htmlspecialchars($d['nombre']) ?>" required />
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2 border-top-0">
                        <a class="btn btn-outline-secondary px-4" href="<?= RUTA_URL ?>departamentos/listar">Cancelar</a>
                        <button class="btn btn-info px-4 fw-bold shadow-sm text-white" type="submit" style="background: #2193b0; border-color: #2193b0;">
                            <i class="bi bi-check-lg me-1"></i> Actualizar
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include("vista/includes/footer_materialize.php"); ?>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: "bootstrap-5",
            width: '100%',
            placeholder: "Seleccione un País",
            allowClear: true
        });
    });
</script>
