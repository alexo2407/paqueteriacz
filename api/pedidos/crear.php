<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Encabezados para CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Authorization');

// Incluir dependencias
require_once __DIR__ . '/../../controlador/pedido.php';
require_once __DIR__ . '/../utils/responder.php';


// Leer el cuerpo de la solicitud
$jsonData = file_get_contents("php://input");

if ($jsonData) {
    $controller = new PedidosController();
    $response = $controller->crearPedidoAPI($jsonData);

    // Responder con los datos
    responder($response['success'], $response['message'], $response['data'] ?? null, $response['success'] ? 201 : 400);
} else {
    responder(false, "Datos no válidos o incompletos", null, 400);
}


?>

<!-- <h1>Hola mudno</h1> -->