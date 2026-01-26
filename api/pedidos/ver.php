<?php
/**
 * GET /api/pedidos/ver
 * Params: id (int)
 */
require_once __DIR__ . '/../../modelo/pedido.php';
require_once __DIR__ . '/../../controlador/pedido.php';
require_once __DIR__ . '/../utils/responder.php';
require_once __DIR__ . '/../utils/autenticacion.php';

$auth = new AuthMiddleware();
if (!$auth->validarToken()['success']) {
    responder(false, "No autorizado", null, 401);
    exit;
}

$idPedido = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$idPedido) {
    responder(false, "ID no especificado", null, 400);
    exit;
}

try {
    $controller = new PedidosController();
    $pedido = $controller->obtenerPedido($idPedido);

    if ($pedido) {
        responder(true, "Detalle del pedido", $pedido, 200);
    } else {
        responder(false, "Pedido no encontrado", null, 404);
    }
} catch (Exception $e) {
    responder(false, "Error: " . $e->getMessage(), null, 500);
}
