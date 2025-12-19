<?php
require_once __DIR__ . '/../../controlador/moneda.php';
require_once __DIR__ . '/../utils/responder.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? null;

if (!$id) {
    responder(false, 'ID de moneda requerido', null, 400);
    exit;
}

try {
    $controller = new MonedasController();
    $data = $controller->ver($id);
    if ($data) {
        responder(true, 'Moneda encontrada', $data, 200);
    } else {
        responder(false, 'Moneda no encontrada', null, 404);
    }
} catch (Exception $e) {
    responder(false, 'Error al obtener moneda: ' . $e->getMessage(), null, 500);
}
