<?php
require_once '../../config/config.php';
require_once '../../modelo/PedidosModel.php';
require_once '../utils/responder.php';

$idPedido = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$idPedido) {
    responder(false, "ID no especificado", null, 400);
    exit;
}

$controller = new PedidosController();
$result = $controller->desactivarPedido($idPedido);

if ($result) {
    responder(true, "Pedido desactivado correctamente", null, 200);
} else {
    responder(false, "Error al desactivar el pedido", null, 500);
}
?>
