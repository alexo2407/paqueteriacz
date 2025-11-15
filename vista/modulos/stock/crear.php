<?php include("vista/includes/header.php"); ?>
<?php
$pathProd = __DIR__ . '/../../../modelo/producto.php';
if (file_exists($pathProd)) {
    require_once $pathProd;
} else {
    // Fallback: intentar ruta relativa antigua por compatibilidad
    @require_once __DIR__ . '/../../modelo/producto.php';
}
$productos = [];
if (class_exists('ProductoModel')) {
    $productos = ProductoModel::listarConInventario();
}
?>

<div class="row">
    <div class="col-sm-8">
        <h3>Nuevo registro de stock</h3>
        <form method="POST" action="<?= RUTA_URL ?>stock/guardar">
            <div class="mb-3">
                <?php
                // Prefill id_usuario from session. Show editable field only for admins.
                $sessionUserId = $_SESSION['user_id'] ?? null;
                $rolesNombres = $_SESSION['roles_nombres'] ?? [];
                $isAdmin = is_array($rolesNombres) && in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
                ?>
                <label class="form-label" for="id_usuario">ID Usuario (propietario)</label>
                <?php if ($isAdmin): ?>
                    <input id="id_usuario" name="id_usuario" type="number" min="1" class="form-control" required value="<?= htmlspecialchars($sessionUserId ?? '') ?>">
                <?php else: ?>
                    <input id="id_usuario" name="id_usuario" type="hidden" value="<?= htmlspecialchars($sessionUserId ?? '') ?>">
                    <input class="form-control" disabled value="<?= htmlspecialchars($sessionUserId ?? 'N/A') ?>">
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="id_producto">Producto</label>
                <select id="id_producto" name="id_producto" class="form-select" required>
                    <option value="">Selecciona un producto</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" data-stock="<?= (int)($p['stock_total'] ?? 0) ?>" data-precio-usd="<?= htmlspecialchars($p['precio_usd']) ?>"><?= htmlspecialchars($p['nombre']) ?><?= isset($p['stock_total']) ? ' — Stock: ' . (int)$p['stock_total'] : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="cantidad">Cantidad</label>
                <input id="cantidad" name="cantidad" type="number" min="0" class="form-control" required>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="<?= RUTA_URL ?>stock/listar" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
