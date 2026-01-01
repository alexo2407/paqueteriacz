<?php
/**
 * GET /api/crm/leads/{id}
 * GET /api/crm/leads/{id}/timeline
 * 
 * Endpoint para ver detalle de un lead o su timeline.
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
    
    $userData = $validacion['data'];
    $userId = (int)$userData['id'];
    
    // Obtener lead_id desde GET
    $leadId = isset($_GET['lead_id']) ? (int)$_GET['lead_id'] : 0;
    
    if ($leadId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'lead_id inválido']);
        exit;
    }
    
    // Obtener lead
    $lead = CrmLeadModel::obtenerPorId($leadId);
    
    if (!$lead) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Lead no encontrado']);
        exit;
    }
    
    // Verificar acceso (ownership o admin)
    $esAdmin = isAdmin($userId);
    $esProveedor = isProveedor($userId) && (int)$lead['proveedor_id'] === $userId;
    $esCliente = isCliente($userId) && (int)$lead['cliente_id'] === $userId;
    
    if (!$esAdmin && !$esProveedor && !$esCliente) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes acceso a este lead']);
        exit;
    }
    
    // Determinar acción
    $action = $_GET['action'] ?? 'detail';
    
    if ($action === 'timeline') {
        // Retornar timeline
        $timeline = CrmLeadModel::obtenerTimeline($leadId);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'lead_id' => $leadId,
            'timeline' => $timeline
        ]);
    } else {
        // Retornar detalle
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'lead' => $lead
        ]);
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
