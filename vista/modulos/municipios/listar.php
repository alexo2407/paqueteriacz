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
    <div class="table-responsive">
        <table class="table table-bordered table-striped dt-responsive tablas" width="100%">
            <thead>
                <tr>
                    <th style="width:10px">#</th>
                    <th>Municipio</th>
                    <th>Departamento</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($municipios)) {
                    foreach ($municipios as $key => $value) {
                        $depName = '';
                        foreach ($departamentos as $d) {
                            if ($d['id'] == $value['id_departamento']) {
                                $depName = $d['nombre'];
                                break;
                            }
                        }
                        echo '<tr>
                                <td>' . ($key + 1) . '</td>
                                <td>' . htmlspecialchars($value["nombre"]) . '</td>
                                <td>' . htmlspecialchars($depName) . '</td>
                                <td>
                                    <div class="btn-group">
                                        <a class="btn btn-sm btn-info" href="' . RUTA_URL . 'municipios/ver/' . urlencode($value['id']) . '">Ver</a>
                                        <a class="btn btn-sm btn-warning" href="' . RUTA_URL . 'municipios/editar/' . urlencode($value['id']) . '">Editar</a>
                                        <form method="post" action="' . RUTA_URL . 'municipios/eliminar/' . urlencode($value['id']) . '" style="display:inline" onsubmit="return confirm(\'Eliminar municipio?\');">
                                            <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>';
                    }
                } else {
                    echo '<tr><td colspan="4">No hay municipios registrados.</td></tr>';
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
