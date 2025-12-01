<?php
include("vista/includes/header.php");

// Obtener productos usando el controlador (consistencia con otros módulos)
$ctrl = new ProductosController();
$productos = $ctrl->listar();
?>
<div class="container">
    <h2>Productos</h2>
    <p><a href="<?= RUTA_URL ?>productos/crear" class="btn btn-primary">Crear producto</a></p>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Precio (USD)</th>
                    <th>Stock total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($productos) && is_array($productos)): ?>
                    <?php foreach ($productos as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['id']) ?></td>
                            <td><?= htmlspecialchars($p['nombre']) ?></td>
                            <td><?= htmlspecialchars($p['descripcion'] ?? '') ?></td>
                            <td><?= htmlspecialchars(number_format((float)($p['precio_usd'] ?? 0), 2)) ?></td>
                            <td><?= htmlspecialchars($p['stock_total'] ?? 0) ?></td>
                            <td>
                                <a class="btn btn-sm btn-info" href="<?= RUTA_URL ?>productos/ver/<?= urlencode($p['id']) ?>">Ver</a>
                                <a class="btn btn-sm btn-warning" href="<?= RUTA_URL ?>productos/editar/<?= urlencode($p['id']) ?>">Editar</a>
                                <form method="post" action="<?= RUTA_URL ?>productos/eliminar/<?= urlencode($p['id']) ?>" style="display:inline" onsubmit="return confirm('Eliminar producto?');">
                                    <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">No hay productos registrados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>
<script>
    $(document).ready(function() {
        $('.table').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
            }
        });
    });
</script>
