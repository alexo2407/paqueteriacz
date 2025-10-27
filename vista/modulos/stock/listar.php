<?php include("vista/includes/header.php"); ?>

<?php
$stockController = new StockController();
$registros = $stockController->listar();
?>

<div class="row">
    <div class="col-sm-6">
        <h3>Inventario</h3>
    </div>
    <div class="col-sm-4 offset-sm-2">
        <a href="<?= RUTA_URL ?>stock/crear" class="btn btn-success w-100"><i class="bi bi-plus-circle-fill"></i> Nuevo registro</a>
    </div>
</div>

<div class="row mt-3 caja">
    <div class="col-sm-12">
        <table id="tablaStock" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ID Vendedor</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['id']); ?></td>
                        <td><?= htmlspecialchars($item['id_vendedor']); ?></td>
                        <td><?= htmlspecialchars($item['producto']); ?></td>
                        <td><?= (int) $item['cantidad']; ?></td>
                        <td>
                            <a href="<?= RUTA_URL ?>stock/editar/<?= $item['id']; ?>" class="btn btn-warning"><i class="bi bi-pencil-fill"></i></a>
                            <form method="POST" action="<?= RUTA_URL ?>stock/eliminar/<?= $item['id']; ?>" class="d-inline" onsubmit="return confirm('¿Eliminar este registro?');">
                                <button type="submit" class="btn btn-danger"><i class="bi bi-trash-fill"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>

<script>
    $(document).ready(function () {
        $('#tablaStock').DataTable();
    });
</script>
