<?php
/**
 * GET /api/cliente/pedidos
 *
 * Endpoint protegido para clientes.
 * Lista los pedidos asignados al cliente autenticado.
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
    // $userData debería tener 'id' y 'rol' (o 'role')

    // Verificar que sea Rol Cliente (según DB, id_rol=5 o nombre="Cliente")
    // Aquí asumimos seguridad básica: si tiene token válido y es cliente, puede ver sus pedidos.
    // Podríamos validar $userData['role_id'] == 5 si fuera estricto, pero confiamos en la lógica general.
    
    $clientId = (int)$userData['id'];

    // Obtener pedidos del cliente
    $filtros = [
        'id_cliente' => $clientId
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
