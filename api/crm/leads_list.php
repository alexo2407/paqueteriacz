<?php
/**
 * GET /api/crm/leads
 * 
 * Endpoint para listar leads con filtros y paginación.
 * Scoping por rol: Proveedor ve sus leads, Cliente los suyos, Admin ve todos.
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
    require_once __DIR__ . '/../utils/autenticacion.php';
    require_once __DIR__ . '/../../utils/crm_roles.php';
    require_once __DIR__ . '/../../modelo/crm_lead.php';
    
    // Validar JWT
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token requerido']);
        exit;
    }
    
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    $auth = new AuthMiddleware();
    $validacion = $auth->validarToken($token);
    
    if (!$validacion['success']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $validacion['message']]);
        exit;
    }
    
    $userData = $validacion['data'];
    $userId = (int)$userData['id'];
    
    // Construir filtros según rol
    $filters = [];
    
    if (isProveedor($userId) && !isAdmin($userId)) {
        $filters['proveedor_id'] = $userId;
    } elseif (isCliente($userId) && !isAdmin($userId)) {
        $filters['cliente_id'] = $userId;
    }
    // Admin no tiene filtro, ve todos
    
    // Filtros adicionales desde query params
    if (!empty($_GET['estado'])) {
        $filters['estado'] = $_GET['estado'];
    }
    
    if (!empty($_GET['fecha_desde'])) {
        $filters['fecha_desde'] = $_GET['fecha_desde'];
    }
    
    if (!empty($_GET['fecha_hasta'])) {
        $filters['fecha_hasta'] = $_GET['fecha_hasta'];
    }
    
    // Paginación
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
    
    // Listar
    $resultado = CrmLeadModel::listar($filters, $page, $limit);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'total' => $resultado['total'],
        'page' => $resultado['page'],
        'limit' => $resultado['limit'],
        'leads' => $resultado['leads']
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
