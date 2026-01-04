<?php
/**
 * POST /api/crm/leads
 * 
 * Endpoint para recibir leads de proveedores.
 * Soporta formato individual {lead: {...}} o batch {leads: [...]}.
 * Responde 202 Accepted inmediatamente y procesa async via inbox.
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
    require_once __DIR__ . '/../../utils/crm_roles.php';
    require_once __DIR__ . '/../../modelo/crm_inbox.php';
    
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
    
    // DEBUG: Log para verificar roles
    $userRoles = getRolesForUser($userId);
    error_log("CRM API - User ID: {$userId}, Roles: " . json_encode($userRoles));
    error_log("CRM API - isAdmin: " . (isUserAdmin($userId) ? 'true' : 'false'));
    error_log("CRM API - isProveedor: " . (isUserProveedor($userId) ? 'true' : 'false'));
    
    // Verificar que es Proveedor o Admin
    if (!isUserProveedor($userId) && !isUserAdmin($userId)) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Acceso denegado: solo proveedores y administradores',
            'debug' => [
                'user_id' => $userId,
                'roles' => $userRoles,
                'is_admin' => isUserAdmin($userId),
                'is_proveedor' => isUserProveedor($userId)
            ]
        ]);
        exit;
    }
    
    // Leer payload
    $rawInput = file_get_contents("php://input");
    $data = json_decode($rawInput, true);
    
    // Log para debugging
    error_log("CRM API - Raw input: " . substr($rawInput, 0, 200));
    error_log("CRM API - Decoded data: " . json_encode($data));
    
    if (!$data || !is_array($data)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Payload inválido: el cuerpo de la solicitud debe ser JSON válido',
            'expected_format' => [
                'individual' => ['lead' => ['proveedor_lead_id' => '...', 'fecha_hora' => '...']],
                'batch' => ['leads' => [['proveedor_lead_id' => '...', 'fecha_hora' => '...']]]
            ],
            'received' => substr($rawInput, 0, 100) . (strlen($rawInput) > 100 ? '...' : '')
        ]);
        exit;
    }
    
    // Detectar formato: individual o batch
    $esIndividual = isset($data['lead']);
    $esBatch = isset($data['leads']) && is_array($data['leads']);
    
    if (!$esIndividual && !$esBatch) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Formato inválido. Use {lead:{...}} o {leads:[...]}'
        ]);
        exit;
    }
    
    // Validar leads
    $leadsParaValidar = $esIndividual ? [$data['lead']] : $data['leads'];
    $erroresValidacion = [];
    
    foreach ($leadsParaValidar as $idx => $lead) {
        if (empty($lead['proveedor_lead_id'])) {
            $erroresValidacion[] = "Lead #{$idx}: proveedor_lead_id es obligatorio";
        }
        if (empty($lead['fecha_hora'])) {
            $erroresValidacion[] = "Lead #{$idx}: fecha_hora es obligatoria";
        }
    }
    
    if (!empty($erroresValidacion)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Errores de validación',
            'errors' => $erroresValidacion
        ]);
        exit;
    }
    
    // Generar idempotency key
    $payload = [
        'proveedor_id' => $userId,
        'lead' => $esIndividual ? $data['lead'] : null,
        'leads' => $esBatch ? $data['leads'] : null
    ];
    
    $idempotencyKey = hash('sha256', json_encode($payload));
    
    // Verificar si ya existe en inbox
    $existingInbox = CrmInboxModel::obtenerPorIdempotencyKey('proveedor', $idempotencyKey);
    
    if ($existingInbox) {
        // Ya existe: retornar 202 sin crear duplicado
        if ($existingInbox['status'] === 'processed') {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Lead(s) ya procesado(s) previamente',
                'accepted' => $esBatch ? count($data['leads']) : 1,
                'duplicated' => true
            ]);
        } else {
            http_response_code(202);
            echo json_encode([
                'success' => true,
                'message' => 'Lead(s) ya encolado(s) para procesamiento',
                'accepted' => $esBatch ? count($data['leads']) : 1,
                'pending' => true
            ]);
        }
        exit;
    }
    
    // Agregar a inbox
    $resultado = CrmInboxModel::agregar('proveedor', $idempotencyKey, $payload);
    
    if (!$resultado['success']) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al encolar lead(s): ' . $resultado['message']
        ]);
        exit;
    }
    
    // Responder 202 Accepted
    http_response_code(202);
    echo json_encode([
        'success' => true,
        'message' => 'Lead(s) aceptado(s) para procesamiento',
        'accepted' => $esBatch ? count($data['leads']) : 1,
        'inbox_id' => $resultado['id']
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
