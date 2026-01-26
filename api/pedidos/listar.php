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
    
    // Obtener parámetros de paginación con valores por defecto
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
    
    // Ejecutar listado paginado (aplica filtros de seguridad automáticamente)
    $result = $controller->listarPedidosPaginados($page, $limit);
    
    // Responder con la data y los metadatos de paginación
    responder(true, 'Listado de pedidos', $result['data'], 200, $result['pagination']);

} catch (Exception $e) {
    responder(false, 'Error al obtener listado de pedidos: ' . $e->getMessage(), null, 500);
}

?>
