<?php
include("vista/includes/header.php");
$ctrl = new PaisesController();
$paises = $ctrl->listar();
?>
<div class="container">
    <h2>Paises</h2>
    <p><a href="<?= RUTA_URL ?>paises/crear" class="btn btn-primary">Crear país</a></p>
    <table class="table table-striped">
        <thead><tr><th>ID</th><th>Nombre</th><th>Código ISO</th><th>Acciones</th></tr></thead>
        <tbody>
            <?php if (!empty($paises)): foreach ($paises as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['id']) ?></td>
                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                    <td><?= htmlspecialchars($p['codigo_iso'] ?? '') ?></td>
                    <td>
                        <a class="btn btn-sm btn-info" href="<?= RUTA_URL ?>paises/ver/<?= urlencode($p['id']) ?>">Ver</a>
                        <a class="btn btn-sm btn-warning" href="<?= RUTA_URL ?>paises/editar/<?= urlencode($p['id']) ?>">Editar</a>
                        <form method="post" action="<?= RUTA_URL ?>paises/eliminar/<?= urlencode($p['id']) ?>" style="display:inline" onsubmit="return confirm('Eliminar país?');">
                            <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4">No hay países registrados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include("vista/includes/footer.php"); ?>
