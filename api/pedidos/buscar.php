<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 

 
 
require_once __DIR__ . '/../utils/autenticacion.php';
require_once __DIR__ . '/../utils/responder.php';
require_once __DIR__ . '/../../controlador/pedido.php';



// Obtener encabezados de la solicitud
$headers = apache_request_headers();

// Autenticación del token
$auth = new AuthMiddleware();
$verificacion = $auth->validarToken($headers);

if (!$verificacion['success']) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $verificacion['message']
    ]);
    exit;
}

// Obtener el número de orden desde la URL
if (!isset($_GET['numero_orden'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Order number is required.'
    ]);
    exit;
}

$numeroOrden = $_GET['numero_orden'];

// Instanciar el controlador de pedidos
$pedidoController = new PedidosController();
$response = $pedidoController->buscarPedidoPorNumero($numeroOrden);

// Responder en formato JSON
header('Content-Type: application/json');
echo json_encode($response);