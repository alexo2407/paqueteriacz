<?php
require_once __DIR__ . '/../../controlador/moneda.php';
require_once __DIR__ . '/../utils/responder.php';

header('Content-Type: application/json');

try {
    $controller = new MonedasController();
    $data = $controller->listar();
    responder(true, 'Listado de monedas', $data, 200);
} catch (Exception $e) {
    responder(false, 'Error al listar monedas: ' . $e->getMessage(), null, 500);
}
