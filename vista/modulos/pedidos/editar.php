<?php include("vista/includes/header.php"); ?>

<div class="container mt-5">
    <h3>Editar Pedido</h3>
    <?php
    $idPedido = $_GET['id']; // Asume que el ID del pedido viene en la URL
    $pedidoController = new PedidosController();
    $pedido = $pedidoController->mostrarPedido($idPedido);
    ?>

    <form action="<?= RUTA_URL ?>pedidos/actualizarPedido/<?php echo $pedido->ID_Pedido; ?>" method="POST">
        <div class="mb-3">
            <label for="Numero_Orden" class="form-label">Número de Orden</label>
            <input type="text" class="form-control" id="Numero_Orden" name="Numero_Orden" value="<?php echo htmlspecialchars($pedido->Numero_Orden); ?>" required>
        </div>
        <div class="mb-3">
            <label for="ID_Cliente" class="form-label">Cliente</label>
            <input type="number" class="form-control" id="ID_Cliente" name="ID_Cliente" value="<?php echo htmlspecialchars($pedido->ID_Cliente); ?>" required>
        </div>
        <!-- Más campos para editar -->
        <div class="mb-3">
            <label for="ID_Estado" class="form-label">Estado</label>
            <select class="form-control" id="ID_Estado" name="ID_Estado" required>
                <option value="1" <?= $pedido->ID_Estado == 1 ? 'selected' : ''; ?>>En Bodega</option>
                <option value="2" <?= $pedido->ID_Estado == 2 ? 'selected' : ''; ?>>En Ruta</option>
                <option value="3" <?= $pedido->ID_Estado == 3 ? 'selected' : ''; ?>>Entregado</option>
                <option value="4" <?= $pedido->ID_Estado == 4 ? 'selected' : ''; ?>>Devuelto</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Actualizar Pedido</button>
    </form>
</div>

<?php include("vista/includes/footer.php"); ?>
