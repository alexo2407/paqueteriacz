<?php
include("vista/includes/header.php"); 

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/
// Obtener el ID del pedido desde la URL

$ruta = isset($_GET['enlace']) ? $_GET['enlace'] : null;

// Dividimos la URL en partes
$pedidoID = explode("/", $ruta );

// Instanciar el controlador
$pedidoController = new PedidosController($pedidoID);



// Si el formulario fue enviado, procesa la actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $pedidoController->guardarEdicion($_POST);

    // Mensajes de éxito o error
    if ($resultado['success']) {
        $mensaje = "<div class='alert alert-success'>Order updated successfully.</div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($resultado['message']) . "</div>";
    }
}

// Obtener los datos actualizados del pedido
$pedido = $pedidoController->obtenerPedido($pedidoID);
$estados = $pedidoController->obtenerEstados();
$vendedores = $pedidoController->obtenerVendedores();


var_dump($pedido);

if (!$pedido) {
    echo "<div class='alert alert-danger'>Order not found.</div>";
    exit;
}
?>

<div class="container mt-4">
    <h2>Edit Order</h2>
    <form method="POST" action="<?= RUTA_URL ?>pedidos/guardarEdicion">
        <input type="hidden" name="id_pedido" value="<?= htmlspecialchars($pedido['id']) ?>">

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="numero_orden" class="form-label">Order Number</label>
                    <input type="text" class="form-control" id="numero_orden" name="numero_orden" value="<?= htmlspecialchars($pedido['numero_orden']) ?>" readonly>
                </div>
                <div class="mb-3">
                    <label for="destinatario" class="form-label">Recipient</label>
                    <input type="text" class="form-control" id="destinatario" name="destinatario" value="<?= htmlspecialchars($pedido['destinatario']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="telefono" class="form-label">Phone</label>
                    <input type="text" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($pedido['telefono']) ?>" required>
                </div>
                <div class="mb-3">
                    <label for="direccion" class="form-label">Address</label>
                    <textarea class="form-control" id="direccion" name="direccion" rows="2" required><?= htmlspecialchars($pedido['direccion']) ?></textarea>
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label for="estado" class="form-label">Status</label>
                    <select class="form-control" id="estado" name="estado" required>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= $estado['id'] ?>" <?= $pedido['id'] == $estado['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado['nombre_estado']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="vendedor" class="form-label">Seller</label>
                    <select class="form-control" id="vendedor" name="vendedor" required>
                        <?php foreach ($vendedores as $vendedor): ?>
                            <option value="<?= $vendedor['id'] ?>" <?= $pedido['id'] == $vendedor['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($vendedor['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="comentario" class="form-label">Comment</label>
                    <textarea class="form-control" id="comentario" name="comentario" rows="2"><?= htmlspecialchars($pedido['comentario']) ?></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="<?= RUTA_URL ?>pedidos/listar" class="btn btn-secondary">Cancel</a>
    </form>
</div>


<?php include("vista/includes/footer.php"); ?>
