<?php
require_once __DIR__ . '/../../controlador/moneda.php';
require_once __DIR__ . '/../utils/responder.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? null;

if (!$id) {
    responder(false, 'ID requerido', null, 400);
    exit;
}

try {
    $controller = new MonedasController();
    $resultado = $controller->eliminar($id);
    
    if ($resultado['success']) {
        responder(true, $resultado['message'], null, 200);
    } else {
        responder(false, $resultado['message'], null, 400);
    }
} catch (Exception $e) {
    responder(false, 'Error al eliminar moneda: ' . $e->getMessage(), null, 500);
}
