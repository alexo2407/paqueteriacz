<?php include("vista/includes/header_materialize.php"); ?>
<?php
require_once __DIR__ . '/../../../controlador/pais.php';
$ctrl = new PaisesController();
$id = null;
if (isset($parametros) && isset($parametros[0])) {
    $id = (int)$parametros[0];
} else {
    $parts = explode('/', $_GET['enlace'] ?? '');
    $id = isset($parts[2]) ? (int)$parts[2] : null;
}
$p = $ctrl->ver($id);
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
}
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <?php if (!$p): ?>
                <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div>
                        <strong>Error:</strong> El país solicitado no existe.
                        <a href="<?= RUTA_URL ?>paises/listar" class="alert-link">Volver al listado</a>
                    </div>
                </div>
            <?php else: ?>
                <form method="post" action="<?= RUTA_URL ?>paises/actualizar/<?= urlencode($p['id']) ?>" class="card form-card">
                    <?php 
                    require_once __DIR__ . '/../../../utils/csrf.php';
                    echo csrf_field(); 
                    ?>
                    
                    <div class="location-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i> Editar País</h3>
                            <p class="mb-0 opacity-75 small">Modificar datos de: <?= htmlspecialchars($p['nombre']) ?></p>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Nombre del País <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light"><i class="bi bi-geo-alt"></i></span>
                                <input class="form-control" name="nombre" value="<?= htmlspecialchars($p['nombre']) ?>" required />
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Código ISO</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-upc-scan"></i></span>
                                <input class="form-control text-uppercase" name="codigo_iso" value="<?= htmlspecialchars($p['codigo_iso'] ?? '') ?>" maxlength="5" />
                            </div>
                            <div class="form-text">Código internacional de 2 o 3 letras (Opcional).</div>
                        </div>
                    </div>

                    <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2 border-top-0">
                        <a class="btn btn-outline-secondary px-4" href="<?= RUTA_URL ?>paises/listar">Cancelar</a>
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
