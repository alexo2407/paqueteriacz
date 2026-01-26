<?php
/**
 * POST /api/pedidos/actualizar
 * Body: JSON with fields to update (must include id)
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

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['id'])) {
    responder(false, "Datos inválidos o ID faltante", null, 400);
    exit;
}

try {
    $controller = new PedidosController();
    $result = $controller->actualizarPedido($input);

    if ($result['success']) {
        responder(true, $result['message'], null, 200);
    } else {
        responder(false, $result['message'], null, 400);
    }
} catch (Exception $e) {
    responder(false, "Error: " . $e->getMessage(), null, 500);
}