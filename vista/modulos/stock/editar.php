<?php include("vista/includes/header.php"); ?>

<?php
$params = isset($parametros) ? $parametros : [];
$idStock = isset($params[0]) ? (int) $params[0] : 0;
$ctrl = new StockController();
$registro = $idStock > 0 ? $ctrl->ver($idStock) : null;
?>

<div class="row">
    <div class="col-sm-8">
        <h3>Editar stock</h3>
        <?php if (!$registro): ?>
            <div class="alert alert-danger">Registro no encontrado.</div>
        <?php else: ?>
            <form method="POST" action="<?= RUTA_URL ?>stock/actualizar/<?= $idStock; ?>">
                <div class="mb-3">
                    <label class="form-label" for="id_vendedor">ID Vendedor</label>
                    <input id="id_vendedor" name="id_vendedor" type="number" min="1" class="form-control" required value="<?= htmlspecialchars($registro['id_vendedor']); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="producto">Producto</label>
                    <input id="producto" name="producto" class="form-control" required value="<?= htmlspecialchars($registro['producto']); ?>">
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
