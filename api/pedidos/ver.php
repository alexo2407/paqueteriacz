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
$token = AuthMiddleware::obtenerTokenDeHeaders();

if (!$token) {
    responder(false, "Token no proporcionado", null, 401);
    exit;
}

$validacion = $auth->validarToken($token);

if (!$validacion['success']) {
    responder(false, "No autorizado", null, 401);
    exit;
}

$idPedido = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$idPedido) {
    responder(false, "ID no especificado", null, 400);
    exit;
}

try {
    $userId = (int)($validacion['data']['id'] ?? 0);
    $userRole = (int)($validacion['data']['rol'] ?? 0);

    $controller = new PedidosController();
    $pedido = $controller->obtenerPedido($idPedido);

    if (!$pedido) {
        responder(false, "Pedido no encontrado", null, 404);
        exit;
    }

    // Ownership verification
    $isAdmin = ($userRole === 1); // ROL_ADMIN
    $isOwner = ((int)$pedido['id_cliente'] === $userId || (int)$pedido['id_proveedor'] === $userId);

    if (!$isAdmin && !$isOwner) {
        responder(false, "ERROR_PERMISOS", ["detail" => "No tienes permiso para ver este pedido."], 403);
        exit;
    }

    responder(true, "Detalle del pedido", $pedido, 200);
} catch (Exception $e) {
    responder(false, "Error: " . $e->getMessage(), null, 500);
}
