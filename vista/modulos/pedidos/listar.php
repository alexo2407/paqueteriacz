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
                          <!-- Botón "Ir a Ruta" -->
                          <?php if (!empty($pedido['latitud']) && !empty($pedido['longitud'])): ?>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $pedido['latitud'] ?>,<?= $pedido['longitud'] ?>&travelmode=driving" 
                                   target="_blank" class="btn btn-success btn-sm">
                                    <i class="bi bi-geo-alt"></i> Ir a Ruta
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm" disabled>
                                    <i class="bi bi-geo-alt"></i> Sin Coordenadas
                                </button>
                            <?php endif; ?>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>


