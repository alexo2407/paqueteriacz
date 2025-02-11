<?php
// Usar rutas absolutas para incluir los archivos necesarios
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../controlador/pedido.php';
require_once __DIR__ . '/../utils/responder.php'; // Archivo para formatear respuestas

// Lee el contenido del cuerpo de la solicitud (JSON enviado desde el cliente)
$jsonData = file_get_contents("php://input");

// Decodifica el JSON para verificar que esté llegando correctamente
if (!$jsonData) {
    echo json_encode([
        "success" => false,
        "message" => "No se recibió ningún JSON válido."
    ]);
    exit;
}

// Enviar los datos al controlador
$controller = new PedidosController();
$result = $controller->crearPedidoAPI($jsonData);

// Responder según el resultado
if ($result) {
    responder(true, "Pedido creado correctamente", ["ID_Pedido" => $result], 201);
} else {
    responder(false, "Error al crear el pedido", null, 500);
}
?>
