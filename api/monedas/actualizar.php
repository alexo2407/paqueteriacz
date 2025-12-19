<?php
require_once __DIR__ . '/../../controlador/moneda.php';
require_once __DIR__ . '/../utils/responder.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? null;
$input = json_decode(file_get_contents('php://input'), true);

if (!$id || !$input) {
    responder(false, 'ID y datos requeridos', null, 400);
    exit;
}

try {
    $controller = new MonedasController();
    $resultado = $controller->actualizar($id, $input);
    
    if ($resultado['success']) {
        responder(true, $resultado['message'], null, 200);
    } else {
        responder(false, $resultado['message'], null, 400);
    }
} catch (Exception $e) {
    responder(false, 'Error al actualizar moneda: ' . $e->getMessage(), null, 500);
}
