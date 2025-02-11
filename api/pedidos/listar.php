<?php
require_once '../../config/config.php';
require_once '../../modelo/PedidosModel.php';
require_once '../utils/responder.php';

$controller = new PedidosController();
$pedidos = $controller->listarPedidos();

responder(true, "Listado de pedidos", $pedidos, 200);
?>
