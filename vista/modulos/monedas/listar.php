<?php
include("vista/includes/header.php");

$ctrl = new MonedasController();
$monedas = $ctrl->listar();
?>
<div class="container">
    <h2>Monedas</h2>
    <p><a href="<?= RUTA_URL ?>monedas/crear" class="btn btn-primary">Crear moneda</a></p>
    <div class="table-responsive">
        <table class="table table-bordered table-striped dt-responsive tablas" width="100%">
            <thead>
                <tr>
                    <th style="width:10px">#</th>
                    <th>Moneda</th>
                    <th>Código</th>
                    <th>Tasa USD</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($monedas as $key => $value) {
                    echo '<tr>
                            <td>' . ($key + 1) . '</td>
                            <td>' . htmlspecialchars($value["nombre"]) . '</td>
                            <td>' . htmlspecialchars($value["codigo"]) . '</td>
                            <td>' . htmlspecialchars($value["tasa_usd"]) . '</td>
                            <td>
                                <div class="btn-group">
                                    <a class="btn btn-sm btn-warning" href="' . RUTA_URL . 'monedas/editar/' . $value["id"] . '">Editar</a>
                                    <form method="post" action="' . RUTA_URL . 'monedas/eliminar/' . $value["id"] . '" style="display:inline" onsubmit="return confirm(\'¿Estás seguro de eliminar esta moneda?\');">
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
