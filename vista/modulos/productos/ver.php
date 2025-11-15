<?php
include("vista/includes/header.php");

$ruta = explode('/', $_GET['enlace'] ?? '');
$id = isset($ruta[2]) ? (int)$ruta[2] : 0;
$ctrl = new ProductosController();
$producto = $ctrl->ver($id);
?>
<div class="container">
    <?php if (empty($producto)): ?>
        <div class="alert alert-danger">Producto no encontrado.</div>
    <?php else: ?>
        <h2><?= htmlspecialchars($producto['nombre']) ?></h2>
        <p><strong>ID:</strong> <?= htmlspecialchars($producto['id']) ?></p>
        <p><strong>Descripción:</strong> <?= nl2br(htmlspecialchars($producto['descripcion'] ?? '')) ?></p>
        <p><strong>Precio (USD):</strong> <?= htmlspecialchars(number_format((float)($producto['precio_usd'] ?? 0),2)) ?></p>
        <p><strong>Stock total:</strong> <?= htmlspecialchars($producto['stock_total'] ?? 0) ?></p>
        <p>
            <a class="btn btn-warning" href="<?= RUTA_URL ?>productos/editar/<?= urlencode($producto['id']) ?>">Editar</a>
            <a class="btn btn-secondary" href="<?= RUTA_URL ?>productos/listar">Volver</a>
        </p>
    <?php endif; ?>
</div>

<?php include("vista/includes/footer.php"); ?>
