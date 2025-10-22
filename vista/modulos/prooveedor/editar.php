<?php include("vista/includes/header.php"); ?>

<?php
// obtener id desde parametros (enlaces model provee parametros)
$params = isset($parametros) ? $parametros : [];
$id = isset($params[0]) ? (int)$params[0] : null;
$provCtrl = new ProveedorController();
$prov = $provCtrl->verProveedor($id);
?>

<div class="row">
    <div class="col-sm-8">
        <h3>Editar Proveedor</h3>
        <?php if (!$prov) : ?>
            <div class="alert alert-danger">Proveedor no encontrado.</div>
        <?php else: ?>
        <form method="POST" action="index.php?enlace=prooveedor/actualizar/<?php echo $id; ?>">
            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input name="nombre" class="form-control" required value="<?php echo htmlspecialchars($prov['nombre']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($prov['email']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Teléfono</label>
                <input name="telefono" class="form-control" value="<?php echo htmlspecialchars($prov['telefono']); ?>">
            </div>
            <button class="btn btn-primary" type="submit">Actualizar</button>
            <a class="btn btn-secondary" href="<?= RUTA_URL ?>prooveedor/listar">Cancelar</a>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
