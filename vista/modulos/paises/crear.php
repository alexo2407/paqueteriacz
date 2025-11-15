<?php
include("vista/includes/header.php");
?>
<div class="container">
    <h2>Crear País</h2>
    <form method="post" action="<?= RUTA_URL ?>paises/guardar">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input class="form-control" name="nombre" required />
        </div>
        <div class="mb-3">
            <label class="form-label">Código ISO</label>
            <input class="form-control" name="codigo_iso" />
        </div>
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>paises/listar">Cancelar</a>
    </form>
</div>
<?php include("vista/includes/footer.php"); ?>
