<?php
/**
 * GET /api/crm/metrics
 * 
 * Endpoint para ver métricas del sistema CRM (solo admin).
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
    require_once __DIR__ . '/../../modelo/crm_outbox.php';
    require_once __DIR__ . '/../../modelo/crm_inbox.php';
    require_once __DIR__ . '/../../modelo/conexion.php';
    
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
    
    // Solo admin
    if (!isAdmin($userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado: solo administradores']);
        exit;
    }
    
    // Obtener métricas
    $db = (new Conexion())->conectar();
    
    // Métricas de leads
    $stmtLeads = $db->query("
        SELECT 
            estado_actual,
            COUNT(*) as count
        FROM crm_leads
        GROUP BY estado_actual
    ");
    $leadsByStatus = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);
    
    // Total de leads
    $totalLeads = (int)$db->query("SELECT COUNT(*) FROM crm_leads")->fetchColumn();
    
    // Métricas de inbox
    $stmtInbox = $db->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM crm_inbox
        GROUP BY status
    ");
    $inbox = $stmtInbox->fetchAll(PDO::FETCH_ASSOC);
    
    // Métricas de outbox
    $outboxMetrics = CrmOutboxModel::obtenerMetricas();
    
    // Últimos eventos
    $stmtRecent = $db->query("
        SELECT id, source, status, received_at
        FROM crm_inbox
        ORDER BY received_at DESC
        LIMIT 10
    ");
    $recentInbox = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtRecentOut = $db->query("
        SELECT id, event_type, status, attempts, created_at
        FROM crm_outbox
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recentOutbox = $stmtRecentOut->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'metrics' => [
            'leads' => [
                'total' => $totalLeads,
                'by_status' => $leadsByStatus
            ],
            'inbox' => $inbox,
            'outbox' => $outboxMetrics,
            'recent' => [
                'inbox' => $recentInbox,
                'outbox' => $recentOutbox
            ]
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
