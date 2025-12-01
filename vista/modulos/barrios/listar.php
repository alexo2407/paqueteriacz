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
    <div class="table-responsive">
        <table class="table table-bordered table-striped dt-responsive tablas" width="100%">
            <thead>
                <tr>
                    <th style="width:10px">#</th>
                    <th>Barrio</th>
                    <th>Municipio</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($barrios)) {
                    foreach ($barrios as $key => $value) {
                        $munName = '';
                        foreach ($municipios as $m) {
                            if ($m['id'] == $value['id_municipio']) {
                                $munName = $m['nombre'];
                                break;
                            }
                        }
                        echo '<tr>
                                <td>' . ($key + 1) . '</td>
                                <td>' . htmlspecialchars($value["nombre"]) . '</td>
                                <td>' . htmlspecialchars($munName) . '</td>
                                <td>
                                    <div class="btn-group">
                                        <a class="btn btn-sm btn-info" href="' . RUTA_URL . 'barrios/ver/' . urlencode($value['id']) . '">Ver</a>
                                        <a class="btn btn-sm btn-warning" href="' . RUTA_URL . 'barrios/editar/' . urlencode($value['id']) . '">Editar</a>
                                        <form method="post" action="' . RUTA_URL . 'barrios/eliminar/' . urlencode($value['id']) . '" style="display:inline" onsubmit="return confirm(\'Eliminar barrio?\');">
                                            <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>';
                    }
                } else {
                    echo '<tr><td colspan="4">No hay barrios registrados.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<?php include("vista/includes/footer.php"); ?>
<script>
    $(document).ready(function() {
        $('.tablas').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
            }
        });
    });
</script>
