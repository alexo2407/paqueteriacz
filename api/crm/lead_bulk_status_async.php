<?php
/**
 * POST /api/crm/leads/bulk-status-async
 * 
 * Endpoint para cambio masivo ASÍNCRONO de estado de múltiples leads.
 * Los clientes pueden enviar miles de leads sin límite.
 * Responde inmediatamente con 202 Accepted y procesa en background.
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/../utils/autenticacion.php';
    require_once __DIR__ . '/../utils/url_helper.php';
    require_once __DIR__ . '/../../utils/crm_roles.php';
    require_once __DIR__ . '/../../utils/crm_status.php';
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
    
    // Verificar que es Cliente o Admin
    if (!isUserCliente($userId) && !isUserAdmin($userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado: solo clientes o administradores']);
        exit;
    }
    
    // Leer payload
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validar campos requeridos
    if (!$data || !isset($data['lead_ids']) || !isset($data['estado'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Campos requeridos: lead_ids (array), estado (string)'
        ]);
        exit;
    }
    
    $leadIds = $data['lead_ids'];
    $estadoNuevo = normalizeEstado($data['estado']);
    $observaciones = $data['observaciones'] ?? null;
    
    // Validar que lead_ids sea un array
    if (!is_array($leadIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'lead_ids debe ser un array']);
        exit;
    }
    
    // Validar que no esté vacío
    if (empty($leadIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'lead_ids no puede estar vacío']);
        exit;
    }
    
    // Validar que todos los IDs sean enteros
    $leadIds = array_map('intval', $leadIds);
    $leadIds = array_filter($leadIds, function($id) { return $id > 0; });
    
    if (empty($leadIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No se proporcionaron lead_ids válidos']);
        exit;
    }
    
    // Validar estado
    if (!isValidEstado($estadoNuevo)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Estado inválido: {$data['estado']}",
            'estados_validos' => getEstadosValidos()
        ]);
        exit;
    }
    
    // ========================================
    // RATE LIMITING
    // ========================================
    require_once __DIR__ . '/../../utils/rate_limiter.php';
    
    $rateLimitCheck = enforceRateLimits($userId, count($leadIds));
    
    if (!$rateLimitCheck['allowed']) {
        http_response_code(429); // Too Many Requests
        echo json_encode([
            'success' => false,
            'error' => 'rate_limit_exceeded',
            'message' => $rateLimitCheck['message'],
            'retry_after' => $rateLimitCheck['retry_after'] ?? null,
            'reset_at' => $rateLimitCheck['reset_at'] ?? null
        ]);
        exit;
    }
    // ========================================
    
    // ========================================
    // PRE-VALIDACIÓN (Feedback Inmediato)
    // ========================================
    require_once __DIR__ . '/../../utils/crm_roles.php';
    
    $db = (new Conexion())->conectar();
    $isAdmin = isUserAdmin($userId);
    
    // Obtener leads para validación previa
    $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
    $stmt = $db->prepare("SELECT id, cliente_id FROM crm_leads WHERE id IN ($placeholders)");
    $stmt->execute($leadIds);
    $existingLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $existingLeadIds = array_column($existingLeads, 'id');
    $validationErrors = [];
    $validLeads = 0;
    
    
    foreach ($leadIds as $leadId) {
        if (!in_array($leadId, $existingLeadIds)) {
            $validationErrors[] = [
                'lead_id' => $leadId,
                'error' => 'Lead no encontrado'
            ];
        } else {
            // Verificar ownership si no es admin
            if (!$isAdmin) {
                // Buscar el lead en el array
                $leadFound = null;
                foreach ($existingLeads as $existingLead) {
                    if ((int)$existingLead['id'] === $leadId) {
                        $leadFound = $existingLead;
                        break;
                    }
                }
                
                if ($leadFound && (int)$leadFound['cliente_id'] !== $userId) {
                    $validationErrors[] = [
                        'lead_id' => $leadId,
                        'error' => 'No tienes permiso para este lead'
                    ];
                } else if ($leadFound) {
                    $validLeads++;
                }
            } else {
                $validLeads++;
            }
        }
    }
    
    // Calcular porcentaje de errores (informativo)
    $errorRate = count($leadIds) > 0 ? count($validationErrors) / count($leadIds) : 0;
    
    // Ya no rechazamos jobs por porcentaje de error
    // El usuario decide si continuar viendo los warnings
    // ========================================
    
    // Generar ID único para el job
    $jobId = 'bulk_' . uniqid() . '_' . time();
    
    // Guardar job en la cola
    $stmt = $db->prepare("
        INSERT INTO crm_bulk_jobs (
            id, user_id, lead_ids, estado, observaciones, 
            status, total_leads, created_at
        ) VALUES (
            :id, :user_id, :lead_ids, :estado, :observaciones,
            'queued', :total_leads, NOW()
        )
    ");
    
    $stmt->execute([
        ':id' => $jobId,
        ':user_id' => $userId,
        ':lead_ids' => json_encode($leadIds),
        ':estado' => $estadoNuevo,
        ':observaciones' => $observaciones,
        ':total_leads' => count($leadIds)
    ]);
    
    // Responder 202 Accepted inmediatamente
    $response = [
        'success' => true,
        'job_id' => $jobId,
        'status' => 'queued',
        'total_leads' => count($leadIds),
        'message' => 'Job encolado para procesamiento',
        'check_status_url' => getApiUrl("crm/jobs/$jobId")
    ];
    
    // Si hay errores, incluir warnings y estadísticas
    if (!empty($validationErrors)) {
        $warningCount = count($validationErrors);
        $leadWord = $warningCount === 1 ? 'lead puede' : 'leads pueden';
        $response['message'] = "Job encolado. $warningCount $leadWord fallar.";
        $response['valid_leads_count'] = $validLeads;
        $response['error_rate'] = round($errorRate * 100, 2) . '%';
        $response['validation_warnings'] = $validationErrors;
    }
    
    http_response_code(202);
    echo json_encode($response);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
    error_log("Error en bulk status async: " . $e->getMessage());
}
