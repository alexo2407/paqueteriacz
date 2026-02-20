<?php
/**
 * POST /api/mensajeria/cambiar_estado
 *
 * Endpoint protegido para proveedores de mensajería.
 * Permite cambiar el estado de un pedido.
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

    // 1. Verificar autenticación
    $token = AuthMiddleware::obtenerTokenDeHeaders();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token de autenticación requerido']);
        exit;
    }

    $auth = new AuthMiddleware();
    $validacion = $auth->validarToken($token);
    
    if (!$validacion['success']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token inválido o expirado']);
        exit;
    }

    $userData = $validacion['data'];
    $clientId = (int)($userData['id'] ?? 0);
    $userRole = (int)($userData['rol'] ?? 0);

    // 2. Verificar Rol (Permitir Admin, Proveedor de Logística y Mensajería)
    // Nota: Los IDs 4 y 5 están intercambiados semánticamente en este proyecto.
    if ($userRole !== ROL_CLIENTE && $userRole !== ROL_PROVEEDOR && $userRole !== ROL_ADMIN) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'ERROR_PERMISOS', 'detail' => 'Tu rol no tiene permiso para acceder a la API de Mensajería']);
        exit;
    }

    // 3. Leer y validar input
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Formato JSON inválido']);
        exit;
    }

    $idPedido = isset($data['id_pedido']) ? (int)$data['id_pedido'] : 0;
    $numeroOrden = isset($data['numero_orden']) ? trim($data['numero_orden']) : null;
    $nuevoEstado = isset($data['estado']) ? (int)$data['estado'] : 0;
    $observaciones = isset($data['motivo']) ? trim($data['motivo']) : 
                    (isset($data['observaciones']) ? trim($data['observaciones']) : null);

    if (($idPedido <= 0 && empty($numeroOrden)) || $nuevoEstado <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros obligatorios: id_pedido o numero_orden, y estado']);
        exit;
    }

    // 4. Delegar lógica de negocio al Modelo (Robusto)
    $resultado = PedidosModel::actualizarEstadoCliente($idPedido ?: null, $clientId, $nuevoEstado, $observaciones, $numeroOrden);

    if ($resultado['success']) {
        http_response_code(200);
        echo json_encode($resultado);
    } else {
        $httpCode = isset($resultado['code']) ? $resultado['code'] : 400;
        http_response_code($httpCode);
        unset($resultado['code']); // Limpiar código interno antes de enviar
        echo json_encode($resultado);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error crítico en el servidor',
        'debug'   => DEBUG ? $e->getMessage() : null
    ]);
}
