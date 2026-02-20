<?php
/**
 * GET /api/mensajeria/pedidos
 *
 * Endpoint protegido para proveedores de mensajería.
 * Lista los pedidos asignados al proveedor autenticado.
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    // Validar token y obtener payload
    $validacion = $auth->validarToken($token);
    
    if (!$validacion['success']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $validacion['message']]);
        exit;
    }

    $userData = $validacion['data']; 
    $userId = (int)$userData['id'];
    $userRole = (int)($userData['rol'] ?? 0);

    // Verificar Rol (Admin, Proveedor o Cliente/Comercio)
    // En este proyecto: Roles 1 (Admin), 4 (Comercio/Creador), 5 (Mensajería)
    // Aunque el usuario ID 10 tenga rol 4, semánticamente es del equipo de mensajería.
    if ($userRole !== ROL_CLIENTE && $userRole !== ROL_PROVEEDOR && $userRole !== ROL_ADMIN) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'ERROR_PERMISOS', 'detail' => 'Tu rol no tiene acceso a esta sección']);
        exit;
    }

    // Obtener pedidos donde el usuario sea el creador o el asignado
    $filtros = [
        'id_usuario_propietario' => $userId
    ];
    
    // Filtros opcionales por query param
    if (isset($_GET['estado'])) {
        $filtros['id_estado'] = (int)$_GET['estado'];
    }

    // Check for pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20; // Default limit 20
    $offset = ($page - 1) * $limit;
    
    // Add pagination to filters
    $filtros['limit'] = $limit;
    $filtros['offset'] = $offset;

    $pedidos = PedidosModel::obtenerConFiltros($filtros);
    $total = PedidosModel::contarConFiltros($filtros);
    $totalPages = ceil($total / $limit);

    echo json_encode([
        'success' => true, 
        'data' => $pedidos,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int)$totalPages
        ]
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage()
    ]);
}
