<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/barrio.php';
require_once __DIR__ . '/../../../controlador/municipio.php';
$munCtrl = new MunicipiosController();
$municipios = $munCtrl->listar();
$ctrl = new BarriosController();
$barrios = $ctrl->listar();
?>
<div class="container">
    <h2>Barrios</h2>
    <p><a href="<?= RUTA_URL ?>barrios/crear" class="btn btn-primary">Crear barrio</a></p>
    <table class="table table-striped">
        <thead><tr><th>ID</th><th>Nombre</th><th>Municipio</th><th>Acciones</th></tr></thead>
        <tbody>
            <?php if (!empty($barrios)): foreach ($barrios as $b):
                $munName = '';
                foreach ($municipios as $m) { if ($m['id'] == $b['id_municipio']) { $munName = $m['nombre']; break; } }
            ?>
                <tr>
                    <td><?= htmlspecialchars($b['id']) ?></td>
                    <td><?= htmlspecialchars($b['nombre']) ?></td>
                    <td><?= htmlspecialchars($munName) ?></td>
                    <td>
                        <a class="btn btn-sm btn-info" href="<?= RUTA_URL ?>barrios/ver/<?= urlencode($b['id']) ?>">Ver</a>
                        <a class="btn btn-sm btn-warning" href="<?= RUTA_URL ?>barrios/editar/<?= urlencode($b['id']) ?>">Editar</a>
                        <form method="post" action="<?= RUTA_URL ?>barrios/eliminar/<?= urlencode($b['id']) ?>" style="display:inline" onsubmit="return confirm('Eliminar barrio?');">
                            <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4">No hay barrios registrados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include("vista/includes/footer.php"); ?>
