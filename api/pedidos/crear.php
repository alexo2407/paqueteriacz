<?php

/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);  */

// Encabezados para CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejo de preflight (solicitudes OPTIONS)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../utils/autenticacion.php';
// require_once __DIR__ . '/../utils/responder.php';
require_once __DIR__ . '/../../controlador/pedido.php';
require_once __DIR__ . '/../../modelo/pedido.php';

// Obtener encabezados de la solicitud
$headers = getallheaders();

if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token requerido']);
    exit;
}

// Extraer el token eliminando 'Bearer '
$token = str_replace('Bearer ', '', $headers['Authorization']);

// Validar el token
$auth = new AuthMiddleware();
$validacion = $auth->validarToken($token);

if (!$validacion['success']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $validacion['message']]);
    exit;
}

// Procesar la solicitud si el token es válido
$data = json_decode(file_get_contents("php://input"), true);

// Validar que los datos sean correctos
if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos o vacíos']);
    exit;
}

$pedidoController = new PedidosController();
$response = $pedidoController->crearPedidoAPI($data);
http_response_code(200);
echo json_encode($response);

?>
