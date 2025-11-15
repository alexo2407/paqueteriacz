<?php include("vista/includes/header.php"); ?>

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

<div class="row">
    <div class="col-sm-8">
        <h3>Editar stock</h3>
        <?php if (!$registro): ?>
            <div class="alert alert-danger">Registro no encontrado.</div>
        <?php else: ?>
            <form method="POST" action="<?= RUTA_URL ?>stock/actualizar/<?= $idStock; ?>">
                <div class="mb-3">
                    <?php
                    $sessionUserId = $_SESSION['user_id'] ?? null;
                    $rolesNombres = $_SESSION['roles_nombres'] ?? [];
                    $isAdmin = is_array($rolesNombres) && in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
                    ?>
                    <label class="form-label" for="id_usuario">ID Usuario (propietario)</label>
                    <?php if ($isAdmin): ?>
                        <input id="id_usuario" name="id_usuario" type="number" min="1" class="form-control" required value="<?= htmlspecialchars($registro['id_usuario'] ?? $sessionUserId ?? '') ?>">
                    <?php else: ?>
                        <input id="id_usuario" name="id_usuario" type="hidden" value="<?= htmlspecialchars($registro['id_usuario'] ?? $sessionUserId ?? '') ?>">
                        <input class="form-control" disabled value="<?= htmlspecialchars($registro['id_usuario'] ?? $sessionUserId ?? 'N/A') ?>">
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="id_producto">Producto</label>
                    <select id="id_producto" name="id_producto" class="form-select" required>
                        <option value="">Selecciona un producto</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= (isset($registro['id_producto']) && (int)$registro['id_producto'] === (int)$p['id']) ? 'selected' : '' ?> ><?= htmlspecialchars($p['nombre']) ?><?= isset($p['stock_total']) ? ' — Stock: ' . (int)$p['stock_total'] : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="cantidad">Cantidad</label>
                    <input id="cantidad" name="cantidad" type="number" min="0" class="form-control" required value="<?= (int) $registro['cantidad']; ?>">
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <a href="<?= RUTA_URL ?>stock/listar" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
