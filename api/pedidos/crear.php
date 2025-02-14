<?php
 /*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); 
*/
// Encabezados para CORS
  header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
  header('Access-Control-Allow-Methods: POST');
  header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Authorization');
 
require_once __DIR__ . '/../utils/autenticacion.php';
require_once __DIR__ . '/../utils/responder.php';
require_once __DIR__ . '/../../controlador/pedido.php';


// Obtener encabezados de la solicitud
$headers = getallheaders();
print_r($headers);
exit;
if (!isset($headers['Authorization'])) {
    echo json_encode(['success' => false, 'message' => 'Token requerido']);
    http_response_code(401);
    exit;
}

// Extraer el token eliminando 'Bearer'
$token = str_replace('Bearer ', '', $headers['Authorization']);


// Validar el token
$auth = new AuthMiddleware();
$validacion = $auth->validarToken($token);

if (!$validacion['success']) {
    echo json_encode(['success' => false, 'message' => $validacion['message']]);
    http_response_code(401);
    exit;
}

// Procesar la solicitud si el token es válido
$data = json_decode(file_get_contents("php://input"), true);
$pedidoController = new PedidosController();
$response = $pedidoController->crearPedidoAPI($data);
echo json_encode($response);

?>

<!-- <h1>Hola mudno</h1> -->

