<?php
include("vista/includes/header.php");
$ctrl = new MonedasController();
?>
<div class="container">
    <h2>Crear Moneda</h2>
    <form method="post" action="<?= RUTA_URL ?>monedas/guardar">
        <div class="mb-3">
            <label class="form-label">Código (ISO)</label>
            <input class="form-control" name="codigo" required />
        </div>
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="nombre" required />
        </div>
        <div class="mb-3">
            <label class="form-label">Tasa USD</label>
            <input class="form-control" name="tasa_usd" />
        </div>
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>monedas/listar">Cancelar</a>
    </form>
</div>

<?php include("vista/includes/footer.php"); ?>
