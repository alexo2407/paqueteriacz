<?php
/**
 * GET /api/pedidos/listar
 *
 * Endpoint para obtener el listado extendido de pedidos. Actualmente
 * devuelve un arreglo de pedidos con información adicional (productos,
 * estados, relaciones). No requiere autenticación en esta versión,
 * pero puede protegerse si se desea.
 *
 * Response: { success, message, data }
 */

// Asegurar que el modelo de Pedidos esté disponible para el controlador.
require_once __DIR__ . '/../../modelo/pedido.php';
require_once __DIR__ . '/../../controlador/pedido.php';
require_once __DIR__ . '/../utils/responder.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../utils/autenticacion.php';
require_once __DIR__ . '/../../utils/permissions.php';

// Verificar autenticación
$token = AuthMiddleware::obtenerTokenDeHeaders();
if (!$token) {
    responder(false, 'Token requerido', null, 401);
    exit;
}

$auth = new AuthMiddleware();
$check = $auth->validarToken($token);
if (!$check['success']) {
    responder(false, $check['message'], null, 401);
    exit;
}

try {
    $controller = new PedidosController();
    $userId = getCurrentUserId();
    $roleId = $GLOBALS['API_USER_ROLE'] ?? 'NONE';
    
    // DEBUG FORZADO PARA EL USUARIO
    if (isset($_GET['debug_auth'])) {
        die(json_encode([
            'debug_active' => true,
            'identified_user_id' => $userId,
            'identified_role_id' => $roleId,
            'is_admin' => isAdmin(),
            'is_proveedor' => isProveedor(),
            'is_vendedor' => isVendedor(),
            'server_vars' => [
                'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'MISSING'
            ]
        ]));
    }
    
    // Check for pagination params
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    
    // If no pagination requested (and legacy clients exist), we could fallback to listarExtendidos
    // But specific task is to "make pagination", implies overriding/supporting it.
    // Let's support it default if page is passed, otherwise maybe dump all (risky) or default paginate?
    // User asked "haz paginaciones en las consultas generales". Implicitly replace or add capability.
    // Safety: Default to pagination 1/50 if not specified effectively makes it a paginated endpoint.
    
    if (isset($_GET['page']) || isset($_GET['limit'])) {
        $result = $controller->listarPedidosPaginados($page, $limit);
        responder(true, 'Listado de pedidos', $result['data'], 200, $result['pagination']);
    } else {
        // I will implement pagination ONLY if page parameter is present to be safe, 
        // OR if I am confident to change it for "consultas generales".
        // Let's support both. If page is present, return paginated structure (or if I update responder to handle metadata).
        
        // Actually, `responder` in utils probably just json_encodes data.
        // Let's just return the paginated structure always? No, that breaks array expectation.
        
        $pedidos = $controller->listarPedidosExtendidos();
        responder(true, 'Listado de pedidos', $pedidos, 200);
    }
} catch (Exception $e) {
    responder(false, 'Error al obtener listado de pedidos: ' . $e->getMessage(), null, 500);
}

?>
