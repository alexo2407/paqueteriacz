<?php
/**
 * GET /api/pedidos/status
 *
 * Endpoint protegido para consultar el estado de los trabajos de logística (validación, etc.)
 * asociados a un pedido.
 *
 * Params:
 *  - numero_orden (required): El número de orden del cliente
 *
 * Headers:
 *  - Authorization: Bearer <token>
 *
 * Response:
 *  - success: bool
 *  - data: array de trabajos (job_type, status, created_at, updated_at)
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../utils/autenticacion.php';
    require_once __DIR__ . '/../../controlador/pedido.php';
    require_once __DIR__ . '/../../services/LogisticsQueueService.php';

    // 1. Verificar autenticación
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token requerido']);
        exit;
    }

    $token = str_replace('Bearer ', '', $headers['Authorization']);
    $auth = new AuthMiddleware();
    $validacion = $auth->validarToken($token);
    
    if (!$validacion['success']) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => $validacion['message']]);
        exit;
    }

    // 2. Validar parámetros (numero_orden O numeros_orden) OR return summary
    $numeros = [];
    $isBatch = false;
    $isSummary = false;

    if (isset($_GET['numeros_orden']) && !empty($_GET['numeros_orden'])) {
        // Modo Batch: lista separada por comas (ej: 1001,1002,1003)
        $numeros = array_map('trim', explode(',', $_GET['numeros_orden']));
        $isBatch = true;
    } elseif (isset($_GET['numero_orden']) && !empty($_GET['numero_orden'])) {
        // Modo Single
        $numeros = [$_GET['numero_orden']];
        $isBatch = false;
    } else {
        // Modo Resumen Automático (Dashboard) - Si no envía parámetros
        $isSummary = true;
    }

    $controller = new PedidosController();
    
    // --> MODO RESUMEN GENERAL (User Dashboard)
    if ($isSummary) {
        $userId = $validacion['data']['id'] ?? 0;
        
        if (!$userId) {
             http_response_code(400);
             echo json_encode(['success' => false, 'message' => 'No se pudo identificar al usuario para el resumen']);
             exit;
        }

        $summary = LogisticsQueueService::obtenerResumenPorProveedor($userId);
        $recentFailures = LogisticsQueueService::obtenerFallidosRecientesPorProveedor($userId, 10);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'mode' => 'provider_dashboard',
            'summary' => $summary,
            'recent_failures' => $recentFailures
        ]);
        exit;
    }

    // --> MODO CONSULTA (Single o Batch)
    $results = [];

    // Limitar batch para evitar sobrecarga (ej. máx 50 a la vez)
    if (count($numeros) > 50) {
        $numeros = array_slice($numeros, 0, 50);
    }

    foreach ($numeros as $numeroOrden) {
        // Buscar el pedido
        $resultadoBusqueda = $controller->buscarPedidoPorNumero($numeroOrden);
        
        $itemStatus = [
            'numero_orden' => $numeroOrden,
            'found' => false,
            'jobs' => []
        ];

        if ($resultadoBusqueda['success'] && !empty($resultadoBusqueda['data'])) {
            $itemStatus['found'] = true;
            $idPedido = $resultadoBusqueda['data']['id'];
            
            // Consultar trabajos
            $trabajos = LogisticsQueueService::obtenerPorPedido($idPedido);
            
            $itemStatus['jobs'] = array_map(function($job) {
                return [
                    'job_type' => $job['job_type'],
                    'status' => $job['status'],
                    'attempts' => (int)$job['attempts'],
                    'updated_at' => $job['updated_at'],
                    'error' => $job['status'] === 'failed' ? $job['last_error'] : null
                ];
            }, $trabajos);
        }
        
        $results[] = $itemStatus;
    }

    http_response_code(200);
    
    if ($isBatch) {
        echo json_encode([
            'success' => true,
            'results' => $results
        ]);
    } else {
        // Mantener formato original para single request
        $single = $results[0];
        if (!$single['found']) {
             http_response_code(404);
             echo json_encode(['success' => false, 'message' => 'Pedido no encontrado']);
             exit;
        }
        echo json_encode([
            'success' => true,
            'numero_orden' => $single['numero_orden'],
            'has_jobs' => count($single['jobs']) > 0,
            'jobs' => $single['jobs']
        ]);
    }

} catch (Throwable $e) {
    error_log("Error en api/pedidos/status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
}
