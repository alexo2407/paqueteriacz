<?php
/**
 * POST /api/pedidos/actualizar
 * Body: JSON with fields to update (must include id)
 */
require_once __DIR__ . '/../../modelo/pedido.php';
require_once __DIR__ . '/../../controlador/pedido.php';
require_once __DIR__ . '/../../controlador/pedidos/PedidoApiController.php';
require_once __DIR__ . '/../utils/responder.php';
require_once __DIR__ . '/../utils/autenticacion.php';

$auth = new AuthMiddleware();
$authResult = $auth->validarToken();

if (!$authResult['success']) {
    responder(false, "No autorizado", null, 401);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['id'])) {
    responder(false, "Datos inválidos o ID faltante", null, 400);
    exit;
}

// Cambiar 'id' a 'id_pedido' para el modelo
$input['id_pedido'] = $input['id'];
unset($input['id']);

try {
    // Obtener datos del usuario autenticado
    $userId = $authResult['user_id'];
    $userRole = $authResult['role'];
    
    // Validar permisos de edición
    $apiController = new PedidoApiController();
    $validacion = $apiController->validarPermisosEdicion(
        $input['id_pedido'], 
        $userId, 
        $userRole, 
        array_keys($input)
    );
    
    if (!$validacion['permitido']) {
        responder(false, $validacion['mensaje'], null, 403);
        exit;
    }
    
    // Si tiene permisos, proceder con la actualización
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