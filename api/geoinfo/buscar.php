<?php
include_once __DIR__ . '/../../config/config.php';
include_once __DIR__ . '/../../controlador/geoinfo.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use GET.', 'code' => 'METHOD_NOT_ALLOWED']);
    exit;
}

$controller = new GeoinfoController();

// Get query parameter
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'El parámetro "q" es obligatorio.',
        'code' => 'MISSING_QUERY'
    ]);
    exit;
}

if (strlen($query) < 2) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'La búsqueda debe tener al menos 2 caracteres.',
        'code' => 'QUERY_TOO_SHORT'
    ]);
    exit;
}

// Get optional filters
$filters = [];
if (isset($_GET['tipo']) && in_array($_GET['tipo'], ['pais', 'departamento', 'municipio', 'barrio'])) {
    $filters['tipo'] = $_GET['tipo'];
}
if (isset($_GET['pais_id']) && is_numeric($_GET['pais_id'])) {
    $filters['pais_id'] = (int)$_GET['pais_id'];
}
if (isset($_GET['departamento_id']) && is_numeric($_GET['departamento_id'])) {
    $filters['departamento_id'] = (int)$_GET['departamento_id'];
}
if (isset($_GET['municipio_id']) && is_numeric($_GET['municipio_id'])) {
    $filters['municipio_id'] = (int)$_GET['municipio_id'];
}

try {
    $results = $controller->buscar($query, $filters);
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'query' => $query,
        'filters' => $filters
    ]);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => 'SEARCH_ERROR'
    ]);
}
