<?php
/**
 * POST /api/cliente/cambiar_estado
 *
 * Endpoint protegido para clientes.
 * Permite cambiar el estado de un pedido asignado al cliente.
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../../utils/autenticacion.php';
    require_once __DIR__ . '/../../modelo/pedido.php';

    // Verificar autenticación
    $token = AuthMiddleware::obtenerTokenDeHeaders();

    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token requerido']);
        exit;
    }

    $auth = new AuthMiddleware();
    $validacion = $auth->validarToken($token);
    
    if (!$validacion['success']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $validacion['message']]);
        exit;
    }

    $userData = $validacion['data'];
    $clientId = (int)$userData['id'];

    // Leer input
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'JSON inválido']);
        exit;
    }

    $idPedido = isset($data['id_pedido']) ? (int)$data['id_pedido'] : 0;
    $nuevoEstado = isset($data['estado']) ? (int)$data['estado'] : 0;
    $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : null;

    if ($idPedido <= 0 || $nuevoEstado <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Faltan datos (id_pedido, estado)']);
        exit;
    }

    // Verificar que el pedido pertenezca al cliente
    $pedido = PedidosModel::obtenerPedidoPorId($idPedido);
    if (!$pedido) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
        exit;
    }

    if ((int)$pedido['id_cliente'] !== $clientId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para modificar este pedido']);
        exit;
    }

    // Validar transiciones permitidas (opcional, pero recomendado)
    // Por ahora permitimos cualquier cambio si es el dueño
    
    // Actualizar estado
    $resultado = PedidosModel::actualizarEstado($idPedido, $nuevoEstado, $observaciones);

    if ($resultado === true || (is_array($resultado) && $resultado['success'])) {
         echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
    } else {
         $msg = is_array($resultado) ? $resultado['message'] : 'No se pudo actualizar el estado';
         echo json_encode(['success' => false, 'message' => $msg]);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage()
    ]);
}
