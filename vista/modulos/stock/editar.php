<?php include("vista/includes/header_materialize.php"); ?>

<?php
$params = isset($parametros) ? $parametros : [];
$idStock = isset($params[0]) ? (int) $params[0] : 0;
$ctrl = new StockController();
$registro = $idStock > 0 ? $ctrl->ver($idStock) : null;
// Cargar modelo Producto desde la raíz del proyecto
$pathProd = __DIR__ . '/../../../modelo/producto.php';
if (file_exists($pathProd)) {
    require_once $pathProd;
} else {
    @require_once __DIR__ . '/../../modelo/producto.php';
}
$productos = [];
if (class_exists('ProductoModel')) {
    $productos = ProductoModel::listarConInventario();
}
?>

<style>
.edit-header {
    background: linear-gradient(135deg, #093028 0%, #237A57 100%);
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
            
            <?php if (!$registro): ?>
                <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div>
                        <strong>Error:</strong> El registro de stock solicitado no existe o no se pudo cargar.
                        <a href="<?= RUTA_URL ?>stock/listar" class="alert-link">Volver al listado</a>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="<?= RUTA_URL ?>stock/actualizar/<?= $idStock; ?>" class="form-card card">
                    <div class="edit-header d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i> Editar Registro de Stock</h3>
                            <p class="mb-0 opacity-75 small">Modificar cantidad o detalles del registro #<?= $idStock ?></p>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <?php
                            $sessionUserId = $_SESSION['user_id'] ?? null;
                            $rolesNombres = $_SESSION['roles_nombres'] ?? [];
                            $isAdmin = is_array($rolesNombres) && in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
                            ?>
                            <label class="form-label fw-bold text-muted small text-uppercase" for="id_usuario">Usuario Propietario</label>
                            <?php if ($isAdmin): ?>
                                <input id="id_usuario" name="id_usuario" type="number" min="1" class="form-control" required value="<?= htmlspecialchars($registro['id_usuario'] ?? $sessionUserId ?? '') ?>">
                                <div class="form-text">ID del usuario asociado a este registro (Solo Admin).</div>
                            <?php else: ?>
                                <input id="id_usuario" name="id_usuario" type="hidden" value="<?= htmlspecialchars($registro['id_usuario'] ?? $sessionUserId ?? '') ?>">
                                <input class="form-control bg-light" disabled value="<?= htmlspecialchars($registro['id_usuario'] ?? $sessionUserId ?? 'N/A') ?>">
                            <?php endif; ?>
                        </div>

                        <div class="row g-4">
                            <div class="col-md-8">
                                <label class="form-label fw-bold" for="id_producto">Producto</label>
                                <select id="id_producto" name="id_producto" class="form-select select2-searchable p-2" required data-placeholder="Buscar producto...">
                                    <option value="">Selecciona un producto</option>
                                    <?php foreach ($productos as $p): ?>
                                        <option value="<?= (int)$p['id'] ?>" <?= (isset($registro['id_producto']) && (int)$registro['id_producto'] === (int)$p['id']) ? 'selected' : '' ?> >
                                            <?= htmlspecialchars($p['nombre']) ?><?= isset($p['stock_total']) ? ' (Stock: ' . (int)$p['stock_total'] . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold" for="cantidad">Cantidad</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-hash"></i></span>
                                    <input id="cantidad" name="cantidad" type="number" min="0" class="form-control fw-bold" required value="<?= (int) $registro['cantidad']; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2 border-top-0">
                        <a href="<?= RUTA_URL ?>stock/listar" class="btn btn-outline-secondary px-4">Cancelar</a>
                        <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">
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
    $('.select2-searchable').select2({
        theme: "bootstrap-5",
        width: '100%',
        dropdownParent: $('.form-card')
    });
});
</script>
