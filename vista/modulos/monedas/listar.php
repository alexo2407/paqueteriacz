<?php
include("vista/includes/header.php");

$ctrl = new MonedasController();
$monedas = $ctrl->listar();
?>
<div class="container">
    <h2>Monedas</h2>
    <p><a href="<?= RUTA_URL ?>monedas/crear" class="btn btn-primary">Crear moneda</a></p>
    <table class="table table-striped">
        <thead>
            <tr><th>ID</th><th>Código</th><th>Nombre</th><th>Tasa USD</th><th>Acciones</th></tr>
        </thead>
        <tbody>
            <?php if (!empty($monedas)): foreach ($monedas as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['id']) ?></td>
                    <td><?= htmlspecialchars($m['codigo']) ?></td>
                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                    <td><?= htmlspecialchars($m['tasa_usd'] ?? '') ?></td>
                    <td>
                        <a class="btn btn-sm btn-info" href="<?= RUTA_URL ?>monedas/ver/<?= urlencode($m['id']) ?>">Ver</a>
                        <a class="btn btn-sm btn-warning" href="<?= RUTA_URL ?>monedas/editar/<?= urlencode($m['id']) ?>">Editar</a>
                        <form method="post" action="<?= RUTA_URL ?>monedas/eliminar/<?= urlencode($m['id']) ?>" style="display:inline" onsubmit="return confirm('Eliminar moneda?');">
                            <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="5">No hay monedas registradas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include("vista/includes/footer.php"); ?>
