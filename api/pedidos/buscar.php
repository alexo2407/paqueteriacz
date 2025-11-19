<?php


header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Authorization');

// Cargar helpers y modelos con rutas relativas correctas desde /api/pedidos
require_once __DIR__ . '/../utils/autenticacion.php';
require_once __DIR__ . '/../utils/responder.php';
// Asegurar que el modelo de Pedidos esté disponible antes de incluir el controlador
require_once __DIR__ . '/../../modelo/pedido.php';
require_once __DIR__ . '/../../controlador/pedido.php';

// Obtener encabezados de la solicitud
$headers = apache_request_headers();

if (!isset($headers['Authorization'])) {
    responder(false, 'Token requerido', null, 401);
    exit;
}

$token = str_replace('Bearer ', '', $headers['Authorization']);
$auth = new AuthMiddleware();
$validacion = $auth->validarToken($token);

if (!$validacion['success']) {
    responder(false, $validacion['message'], null, 403);
    exit;
}

// Obtener el número de orden desde la URL
$numeroOrden = $_GET['numero_orden'] ?? null;

if (!$numeroOrden) {
    responder(false, 'Order number is required', null, 400);
    exit;
}

$pedidoController = new PedidosController();
$response = $pedidoController->buscarPedidoPorNumero($numeroOrden);

// Usar el helper responder() para mantener el sobre de respuesta consistente
if (isset($response['success']) && $response['success']) {
    responder(true, $response['message'] ?? 'Pedido encontrado', $response['data'] ?? null, 200);
} else {
    responder(false, $response['message'] ?? 'Pedido no encontrado', null, 404);
}
?>
