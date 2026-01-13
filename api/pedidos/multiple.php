<?php
/**
 * POST /api/pedidos/multiple
 *
 * Endpoint protegido para crear múltiples pedidos en lote.
 * Requiere header Authorization: Bearer <JWT>.
 * El body debe ser JSON con la clave `pedidos` que contiene un array de pedidos.
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../utils/autenticacion.php';
require_once __DIR__ . '/../../controlador/pedido.php';

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

// Delegar todo el trabajo de lectura/validación/inserción al controlador
$userId = $validacion['data']['id'] ?? 0;
$userRole = $validacion['data']['rol'] ?? '';

$controller = new PedidosController();
$controller->createMultiple($userId, $userRole);

?>
