<?php
/* 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */

// Encabezados para CORS
  header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
  header('Access-Control-Allow-Methods: POST');
  header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Authorization');
 
require_once __DIR__ . '/../utils/autenticacion.php';
require_once __DIR__ . '/../utils/responder.php';
require_once __DIR__ . '/../../controlador/pedido.php';


// Obtener el token del encabezado Authorization
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    responder(false, 'Token requerido en el encabezado Authorization', null, 401);
    exit;
}

$token = str_replace('Bearer ', '', $headers['Authorization']);

// Validar el token
$auth = new AuthMiddleware();
$validacion = $auth->validarToken($token);

if (!$validacion['success']) {
    responder(false, $validacion['message'], null, 401);
    exit;
}

// Procesar la creación del pedido si el token es válido
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['numero_orden']) || !isset($data['destinatario'])) {
    responder(false, 'Faltan parámetros requeridos', null, 400);
    exit;
}

// Llamar al controlador de pedidos
$pedidoController = new PedidosController();
$response = $pedidoController->crearPedidoAPI($data);

if ($response['success']) {
    responder(true, 'Pedido creado con éxito', $response['data']);
} else {
    responder(false, $response['message'], null, 500);
}


?>

<!-- <h1>Hola mudno</h1> -->

