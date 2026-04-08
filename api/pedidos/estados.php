<?php
/**
 * API Endpoint: GET /api/pedidos/estados
 * 
 * Returns all available order states
 * Public endpoint - no authentication required
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/../utils/responder.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responder(false, 'Método no permitido. Use GET.', null, 405);
}

try {
    // Include required files
    require_once __DIR__ . '/../../modelo/pedido.php';
    
    // Get all order states
    $estados = PedidosModel::obtenerEstados();
    
    responder(true, 'Listado de estados de pedido', $estados, 200);
    
} catch (Exception $e) {
    error_log('[api/pedidos/estados] Error: ' . $e->getMessage());
    responder(false, 'Error interno del servidor.', null, 500);
}
