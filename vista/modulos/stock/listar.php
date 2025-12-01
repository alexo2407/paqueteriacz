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
        <div class="table-responsive">
            <table id="tablaStock" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $item): ?>
                        <?php 
                            $cantidad = (int) $item['cantidad'];
                            $colorClass = $cantidad >= 0 ? 'text-success' : 'text-danger';
                            $signo = $cantidad > 0 ? '+' : '';
                            $fecha = !empty($item['updated_at']) ? date('d/m/Y H:i', strtotime($item['updated_at'])) : '—';
                            $usuario = !empty($item['usuario']) ? $item['usuario'] : ($item['id_usuario'] ? 'ID: '.$item['id_usuario'] : 'Sistema');
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($item['id']); ?></td>
                            <td><?= htmlspecialchars($usuario); ?></td>
                            <td><?= htmlspecialchars($item['producto'] ?? ($item['producto_nombre'] ?? '')); ?></td>
                            <td class="<?= $colorClass; ?> fw-bold"><?= $signo . $cantidad; ?></td>
                            <td><?= $fecha; ?></td>
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
</div>

<?php include("vista/includes/footer.php"); ?>

<script>
    $(document).ready(function () {
        $('#tablaStock').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
            }
        });
    });
</script>
