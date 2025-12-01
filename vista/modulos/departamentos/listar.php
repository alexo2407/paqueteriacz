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
    <div class="table-responsive">
        <table class="table table-bordered table-striped dt-responsive tablas" width="100%">
            <thead>
                <tr>
                    <th style="width:10px">#</th>
                    <th>Departamento</th>
                    <th>País</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($departamentos as $key => $value) {
                    // Find the country name for the current department
                    $pais_nombre = '';
                    foreach ($paises as $p) {
                        if ($p['id'] == $value['id_pais']) {
                            $pais_nombre = $p['nombre'];
                            break;
                        }
                    }

                    echo '<tr>
                            <td>' . ($key + 1) . '</td>
                            <td>' . htmlspecialchars($value["nombre"]) . '</td>
                            <td>' . htmlspecialchars($pais_nombre) . '</td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-sm btn-info" href="' . RUTA_URL . 'departamentos/ver/' . urlencode($value['id']) . '">Ver</a>
                                    <a class="btn btn-sm btn-warning" href="' . RUTA_URL . 'departamentos/editar/' . urlencode($value['id']) . '">Editar</a>
                                    <form method="post" action="' . RUTA_URL . 'departamentos/eliminar/' . urlencode($value['id']) . '" style="display:inline" onsubmit="return confirm(\'Eliminar departamento?\');">
                                        <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>';
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
