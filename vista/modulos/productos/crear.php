<?php
include("vista/includes/header.php");
?>
<div class="container">
    <h2>Crear Producto</h2>
    <form method="post" action="<?= RUTA_URL ?>productos/guardar">
        <div class="form-group">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" class="form-control" required />
        </div>
        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" class="form-control"></textarea>
        </div>
        <div class="form-group">
            <label for="precio_usd">Precio (USD)</label>
            <input id="precio_usd" name="precio_usd" class="form-control" type="number" step="0.01" />
        </div>
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>productos/listar">Cancelar</a>
    </form>
</div>

<?php include("vista/includes/footer.php"); ?>
