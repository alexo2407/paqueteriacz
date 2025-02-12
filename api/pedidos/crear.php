<?php


 //Encabezados (HEADERS)
 header('Access-Control-Allow-Origin: *');
 header('Content-Type: application/json');
 header('Access-Control-Allow-Methods: POST');
 header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');


 require_once __DIR__ . '/../../controlador/pedido.php';
 require_once __DIR__ . '/../utils/responder.php';
 
 // Leer el cuerpo de la solicitud (JSON)
 $jsonData = file_get_contents("php://input");
 
 if ($jsonData) {
     $controller = new PedidosController();
     $response = $controller->crearPedidoAPI($jsonData);
 
     // Responder según el resultado
     responder($response["success"], $response["message"], $response["data"] ?? null, $response["success"] ? 201 : 400);
 } else {
     responder(false, "Datos no válidos o incompletos", null, 400);
 }
 