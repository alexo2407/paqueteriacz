<?php
include("vista/includes/header.php");
require_once __DIR__ . '/../../../controlador/departamento.php';
require_once __DIR__ . '/../../../controlador/pais.php';
$paisCtrl = new PaisesController();
$paises = $paisCtrl->listar();
$ctrl = new DepartamentosController();
$departamentos = $ctrl->listar();
?>
<div class="container">
    <h2>Departamentos</h2>
    <p><a href="<?= RUTA_URL ?>departamentos/crear" class="btn btn-primary">Crear departamento</a></p>
    <table class="table table-striped">
        <thead><tr><th>ID</th><th>Nombre</th><th>País</th><th>Acciones</th></tr></thead>
        <tbody>
            <?php if (!empty($departamentos)): foreach ($departamentos as $d):
                $pais = null; foreach ($paises as $pt) { if ($pt['id'] == $d['id_pais']) { $pais = $pt['nombre']; break; } }
            ?>
                <tr>
                    <td><?= htmlspecialchars($d['id']) ?></td>
                    <td><?= htmlspecialchars($d['nombre']) ?></td>
                    <td><?= htmlspecialchars($pais ?? '') ?></td>
                    <td>
                        <a class="btn btn-sm btn-info" href="<?= RUTA_URL ?>departamentos/ver/<?= urlencode($d['id']) ?>">Ver</a>
                        <a class="btn btn-sm btn-warning" href="<?= RUTA_URL ?>departamentos/editar/<?= urlencode($d['id']) ?>">Editar</a>
                        <form method="post" action="<?= RUTA_URL ?>departamentos/eliminar/<?= urlencode($d['id']) ?>" style="display:inline" onsubmit="return confirm('Eliminar departamento?');">
                            <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="4">No hay departamentos registrados.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php include("vista/includes/footer.php"); ?>
