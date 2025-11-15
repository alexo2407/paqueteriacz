<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/municipio.php';
require_once __DIR__ . '/../../../controlador/departamento.php';
$depCtrl = new DepartamentosController();
$departamentos = $depCtrl->listar();
$ctrl = new MunicipiosController();
$municipios = $ctrl->listar();
?>
<div class="container">
    <h2>Municipios</h2>
    <p><a href="<?= RUTA_URL ?>municipios/crear" class="btn btn-primary">Crear municipio</a></p>
    <table class="table table-striped">
        <thead><tr><th>ID</th><th>Nombre</th><th>Departamento</th><th>Acciones</th></tr></thead>
        <tbody>
            <?php if (!empty($municipios)): foreach ($municipios as $m):
                $depName = '';
                foreach ($departamentos as $d) { if ($d['id'] == $m['id_departamento']) { $depName = $d['nombre']; break; } }
            ?>
                <tr>
                    <td><?= htmlspecialchars($m['id']) ?></td>
                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                    <td><?= htmlspecialchars($depName) ?></td>
                    <td>
                        <a class="btn btn-sm btn-info" href="<?= RUTA_URL ?>municipios/ver/<?= urlencode($m['id']) ?>">Ver</a>
                        <a class="btn btn-sm btn-warning" href="<?= RUTA_URL ?>municipios/editar/<?= urlencode($m['id']) ?>">Editar</a>
                        <form method="post" action="<?= RUTA_URL ?>municipios/eliminar/<?= urlencode($m['id']) ?>" style="display:inline" onsubmit="return confirm('Eliminar municipio?');">
                            <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4">No hay municipios registrados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include("vista/includes/footer.php"); ?>
