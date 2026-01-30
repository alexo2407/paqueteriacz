<?php include("vista/includes/header.php"); ?>
<?php 
require_once __DIR__ . '/../../../controlador/barrio.php';
require_once __DIR__ . '/../../../controlador/municipio.php';
$ctrl = new BarriosController();
$munCtrl = new MunicipiosController();
$municipios = $munCtrl->listar();

$id = null;
if (isset($parametros) && isset($parametros[0])) {
    $id = (int)$parametros[0];
} else {
    $parts = explode('/', $_GET['enlace'] ?? '');
    $id = isset($parts[2]) ? (int)$parts[2] : null;
}
$b = $ctrl->ver($id);
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
            <?php if (!$b): ?>
                <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div>
                        <strong>Error:</strong> El barrio solicitado no existe.
                        <a href="<?= RUTA_URL ?>barrios/listar" class="alert-link">Volver al listado</a>
                    </div>
                </div>
            <?php else: ?>
                <form method="post" action="<?= RUTA_URL ?>barrios/actualizar/<?= urlencode($b['id']) ?>" class="card form-card">
                    <?php 
                    require_once __DIR__ . '/../../../utils/csrf.php';
                    echo csrf_field(); 
                    ?>
                    
                    <div class="location-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i> Editar Barrio</h3>
                            <p class="mb-0 opacity-75 small">Modificar datos de: <?= htmlspecialchars($b['nombre']) ?></p>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Municipio Perteneciente <span class="text-danger">*</span></label>
                            <select name="id_municipio" class="form-select select2" required style="width: 100%;">
                                <option value="">-- Seleccione un Municipio --</option>
                                <?php foreach ($municipios as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= $m['id']==$b['id_municipio']? 'selected':'' ?>><?= htmlspecialchars($m['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Cambiar el municipio reasignará el barrio.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Nombre del Barrio <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light"><i class="bi bi-geo"></i></span>
                                <input class="form-control" name="nombre" value="<?= htmlspecialchars($b['nombre']) ?>" required />
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Código Postal</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-mailbox"></i></span>
                                <input class="form-control" name="codigo_postal" value="<?= htmlspecialchars($b['codigo_postal'] ?? '') ?>" placeholder="Ej: 10101, 10102..." />
                            </div>
                            <div class="form-text">Opcional. Se usará si el país define CP a nivel de barrio (como Costa Rica).</div>
                        </div>
                    </div>

                    <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2 border-top-0">
                        <a class="btn btn-outline-secondary px-4" href="<?= RUTA_URL ?>barrios/listar">Cancelar</a>
                        <button class="btn btn-info px-4 fw-bold shadow-sm text-white" type="submit" style="background: #2193b0; border-color: #2193b0;">
                            <i class="bi bi-check-lg me-1"></i> Actualizar
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>

<script>
    $(document).ready(function() {
        $('.select2').select2({
            theme: "bootstrap-5",
            width: '100%',
            placeholder: "Seleccione un Municipio",
            allowClear: true
        });
    });
</script>
