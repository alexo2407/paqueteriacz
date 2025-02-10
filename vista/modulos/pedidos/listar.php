<?php include("vista/includes/header.php"); ?>

<div class="row">
    <div class="col-sm-6">
        <h3>Lista de Pedidos</h3>
    </div>
    <div class="col-sm-6 text-end">
        <a href="<?= RUTA_URL ?>pedidos/crearPedido" class="btn btn-success"><i class="bi bi-plus-circle-fill"></i> Nuevo Pedido</a>
    </div>
</div>

<div class="row mt-2 caja">
    <div class="col-sm-12">
        <table id="tblPedidos" class="table table-striped" style="width:100%">
            <thead>
                <tr>
                    <th>ID Pedido</th>
                    <th>Número de Orden</th>
                    <th>Cliente</th>
                    <th>Comentario</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $listarPedidos = new PedidosController();
                $pedidos = $listarPedidos->listarPedidosExtendidos();

                foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pedido['ID_Pedido']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Numero_Orden']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Cliente']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Comentario']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['Estado']); ?></td>
                        <td>
                            <a href="<?= RUTA_URL ?>pedidos/ver/<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-primary btn-sm">Ver</a>
                            <a href="<?= RUTA_URL ?>pedidos/editar/<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-warning btn-sm">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>


