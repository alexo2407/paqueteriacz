<?php 
include("vista/includes/header.php"); 

// Llamamos al controlador para obtener los datos del pedido
if (isset($_GET['idPedido'])) {
    $idPedido = intval($_GET['idPedido']); // Asegúrate de que sea un número entero válido

    if (!is_numeric($idPedido) || $idPedido <= 0) {
        die("ID de pedido no válido.");
    }
    else {

        $pedidoExtendido = new PedidosController();
        $detallesPedido = $pedidoExtendido->verPedido($idPedido);
    }
    
   
} else {
    $detallesPedido = [];
}
?>

<div class="container mt-4">
    <h3>Detalles del Pedido</h3>
    <hr>
    <?php 
    // var_dump($detallesPedido);
    if (!empty($detallesPedido)): ?>
        <?php $pedido = $detallesPedido[0]; ?>
        <div class="row">
            <div class="col-md-6">
                <h5>Información del Pedido</h5>
                <p><strong>ID Pedido:</strong> <?php echo htmlspecialchars($pedido['ID_Pedido']); ?></p>
                <p><strong>Número de Orden:</strong> <?php echo htmlspecialchars($pedido['Numero_Orden']); ?></p>
                <p><strong>Fecha de Ingreso:</strong> <?php echo htmlspecialchars($pedido['Fecha_Ingreso']); ?></p>
                <p><strong>Comentario:</strong> <?php echo htmlspecialchars($pedido['Comentario']); ?></p>
                <p><strong>Estado:</strong> <?php echo htmlspecialchars($pedido['Estado']); ?></p>
            </div>
            <div class="col-md-6">
                <h5>Ubicación</h5>
                <p><strong>Zona:</strong> <?php echo htmlspecialchars($pedido['Zona']); ?></p>
                <p><strong>Departamento:</strong> <?php echo htmlspecialchars($pedido['Departamento']); ?></p>
                <p><strong>Municipio:</strong> <?php echo htmlspecialchars($pedido['Municipio']); ?></p>
                <p><strong>Barrio:</strong> <?php echo htmlspecialchars($pedido['Barrio']); ?></p>
                <p><strong>Dirección Completa:</strong> <?php echo htmlspecialchars($pedido['Direccion_Completa']); ?></p>
                <p><strong>Coordenadas:</strong> <?php echo htmlspecialchars($pedido['COORDINATES']); ?></p>
            </div>
        </div>
        <hr>
        <h5>Cliente</h5>
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pedido['Cliente']); ?></p>
        <hr>
        <h5>Usuario Responsable</h5>
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pedido['Usuario']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($pedido['UsuarioEmail']); ?></p>
        <hr>
        <h5>Productos del Pedido</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-striped dt-responsive tablas" width="100%">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio Unitario</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Assuming $detallesPedido contains product details in a similar structure to the instruction's $productosPedido
                    // Adjusting to match the existing $detallesPedido structure for products
                    foreach ($detallesPedido as $producto) {
                        echo '<tr>
                                <td>' . htmlspecialchars($producto["Producto"]) . '</td>
                                <td>' . htmlspecialchars($producto["Cantidad"]) . '</td>
                                <td>' . htmlspecialchars(number_format($producto["Precio"], 2)) . '</td>
                                <td>' . htmlspecialchars(number_format($producto["Precio"] * $producto["Cantidad"], 2)) . '</td>
                            </tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">No se encontraron detalles para este pedido.</div>
    <?php endif; ?>
    <a href="<?= RUTA_URL ?>pedidos" class="btn btn-primary">Volver</a>
</div>

<?php include("vista/includes/footer.php"); ?>
