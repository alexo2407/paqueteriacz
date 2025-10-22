<?php include("vista/includes/header.php"); ?>

<div class="row">
    <div class="col-sm-8">
        <h3>Crear Proveedor</h3>
        <form method="POST" action="index.php?enlace=prooveedor/guardar">
            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input name="nombre" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input name="email" type="email" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Teléfono</label>
                <input name="telefono" class="form-control">
            </div>
            <button class="btn btn-primary" type="submit">Guardar</button>
            <a class="btn btn-secondary" href="<?= RUTA_URL ?>prooveedor/listar">Cancelar</a>
        </form>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
