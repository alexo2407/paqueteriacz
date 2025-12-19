<?php
include("vista/includes/header.php");

// Determinar ID desde la ruta: ?enlace=productos/editar/<id>
$ruta = explode('/', $_GET['enlace'] ?? '');
$id = isset($ruta[2]) ? (int)$ruta[2] : 0;
$ctrl = new ProductosController();
$producto = $ctrl->ver($id);
?>
<div class="container">
    <h2>Editar Producto</h2>
    <?php if (empty($producto)): ?>
        <div class="alert alert-danger">Producto no encontrado.</div>
    <?php else: ?>
    <form method="post" action="<?= RUTA_URL ?>productos/actualizar/<?= urlencode($producto['id']) ?>">
        <?php 
        require_once __DIR__ . '/../../../utils/csrf.php';
        echo csrf_field(); 
        ?>
        <div class="form-group">
            <label for="nombre">Nombre</label>
            <input id="nombre" name="nombre" class="form-control" required value="<?= htmlspecialchars($producto['nombre']) ?>" />
        </div>
        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" class="form-control"><?= htmlspecialchars($producto['descripcion'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="precio_usd">Precio (USD)</label>
            <input id="precio_usd" name="precio_usd" class="form-control" type="number" step="0.01" value="<?= htmlspecialchars($producto['precio_usd'] ?? '') ?>" />
        </div>
        <button class="btn btn-primary" type="submit">Actualizar</button>
        <a class="btn btn-secondary" href="<?= RUTA_URL ?>productos/listar">Cancelar</a>
    </form>
    <?php endif; ?>
</div>

<?php include("vista/includes/footer.php"); ?>
