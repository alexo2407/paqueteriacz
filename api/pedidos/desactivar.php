<?php
require_once '../../config/config.php';
require_once '../../modelo/conexion.php';
require_once '../../modelo/pedido.php';
require_once '../../controlador/pedido.php';
require_once '../utils/responder.php';
require_once '../utils/autenticacion.php';

$auth = new AuthMiddleware();
$authResult = $auth->validarToken();

if (!$authResult['success']) {
    responder(false, "No autorizado", null, 401);
    exit;
}

$idPedido = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$idPedido) {
    responder(false, "ID no especificado", null, 400);
    exit;
}

try {
    $userId = (int)($authResult['data']['id'] ?? 0);
    $userRole = (int)($authResult['data']['rol'] ?? 0);

    $controller = new PedidosController();
    $pedido = $controller->obtenerPedido($idPedido);

    if (!$pedido) {
        responder(false, "Pedido no encontrado", null, 404);
        exit;
    }

    // Only Admin can deactivate? 
    $isAdmin = ($userRole === 1);
    
    if (!$isAdmin) {
        responder(false, "ERROR_PERMISOS", ["detail" => "Solo administradores pueden desactivar pedidos"], 403);
        exit;
    }

    $result = $controller->desactivarPedido($idPedido);

    if ($result) {
        responder(true, "Pedido desactivado correctamente", null, 200);
    } else {
        responder(false, "Error al desactivar el pedido", null, 500);
    }
} catch (Exception $e) {
    responder(false, "Error: " . $e->getMessage(), null, 500);
}
?>
