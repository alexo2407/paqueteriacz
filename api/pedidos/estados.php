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

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Use GET.'
    ]);
    exit;
}

try {
    // Include required files
    require_once __DIR__ . '/../../modelo/pedido.php';
    
    // Get all order states
    $estados = PedidosModel::obtenerEstados();
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $estados
    ]);
    
} catch (Exception $e) {
    // Handle errors
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener estados',
        'error' => $e->getMessage()
    ]);
}
