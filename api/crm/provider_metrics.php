<?php
/**
 * GET /api/crm/provider-metrics
 * 
 * Endpoint para obtener métricas de leads del proveedor autenticado.
 * Para uso externo via API (sistemas del proveedor).
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
    require_once __DIR__ . '/../../utils/crm_roles.php';
    require_once __DIR__ . '/../../modelo/crm_lead.php';
    
    // Validar JWT
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Token requerido'
        ]);
        exit;
    }
    
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    $auth = new AuthMiddleware();
    $validacion = $auth->validarToken($token);
    
    if (!$validacion['success']) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Token inválido o expirado'
        ]);
        exit;
    }
    
    $userData = $validacion['data'];
    $userId = (int)$userData['id'];
    
    // Verificar que sea proveedor o admin
    if (!isUserProveedor($userId) && !isUserAdmin($userId)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Acceso denegado: solo proveedores'
        ]);
        exit;
    }
    
    // Obtener métricas
    $metricas = CrmLeadModel::obtenerMetricasProveedor($userId);
    
    // Calcular porcentaje
    $total = $metricas['total'];
    $procesados = $metricas['procesados'];
    $porcentajeProcesado = $total > 0 ? round(($procesados / $total) * 100, 2) : 0;
    
    // Responder
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'total' => $total,
            'procesados' => $procesados,
            'en_espera' => $metricas['en_espera'],
            'porcentaje_procesado' => $porcentajeProcesado,
            'por_estado' => $metricas['por_estado']
        ]
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
