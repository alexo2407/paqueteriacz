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

$data = json_decode($jsonData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error en el formato del JSON: " . json_last_error_msg());
}


try {
    $controller = new PedidosController();
    $response = $controller->crearPedidoAPI($jsonData);

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error interno: " . $e->getMessage()
    ]);
}



?>

<!-- <h1>Hola mudno</h1> -->