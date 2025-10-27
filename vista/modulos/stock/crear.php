<?php include("vista/includes/header.php"); ?>

<div class="row">
    <div class="col-sm-8">
        <h3>Nuevo registro de stock</h3>
        <form method="POST" action="<?= RUTA_URL ?>stock/guardar">
            <div class="mb-3">
                <label class="form-label" for="id_vendedor">ID Vendedor</label>
                <input id="id_vendedor" name="id_vendedor" type="number" min="1" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="producto">Producto</label>
                <input id="producto" name="producto" class="form-control" required>
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
