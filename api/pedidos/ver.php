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
$pedido = $controller->verPedido($idPedido);

if ($pedido) {
    responder(true, "Detalles del pedido", $pedido, 200);
} else {
    responder(false, "Pedido no encontrado", null, 404);
}
?>
