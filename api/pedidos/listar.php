<?php
require_once __DIR__ . '/../../controlador/pedido.php';
require_once __DIR__ . '/../utils/responder.php';

header('Content-Type: application/json');

try {
	$controller = new PedidosController();
	$pedidos = $controller->listarPedidosExtendidos();
	responder(true, 'Listado de pedidos', $pedidos, 200);
} catch (Exception $e) {
	responder(false, 'Error al obtener listado de pedidos: ' . $e->getMessage(), null, 500);
}

?>
