<?php
include("vista/includes/header.php");
$ctrl = new PaisesController();
$paises = $ctrl->listar();
?>
<div class="container">
    <h2>Paises</h2>
    <p><a href="<?= RUTA_URL ?>paises/crear" class="btn btn-primary">Crear país</a></p>
            <div class="table-responsive">
                <table class="table table-bordered table-striped dt-responsive tablas" width="100%">
                    <thead>
                        <tr>
                            <th style="width:10px">#</th>
                            <th>País</th>
                            <th>Código</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($paises as $key => $value) {
                            echo '<tr>
                                    <td>' . ($key + 1) . '</td>
                                    <td>' . htmlspecialchars($value["nombre"]) . '</td>
                                    <td>' . htmlspecialchars($value["codigo_iso"] ?? '') . '</td>
                                    <td>
                                        <div class="btn-group">
                                            <a class="btn btn-sm btn-info" href="' . RUTA_URL . 'paises/ver/' . urlencode($value['id']) . '">Ver</a>
                                            <a class="btn btn-sm btn-warning" href="' . RUTA_URL . 'paises/editar/' . urlencode($value['id']) . '">Editar</a>
                                            <form method="post" action="' . RUTA_URL . 'paises/eliminar/' . urlencode($value['id']) . '" style="display:inline" onsubmit="return confirm(\'¿Eliminar país?\');">
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
