<?php
/**
 * POST /api/crm/leads/{id}/estado
 * 
 * Endpoint para que clientes actualicen el estado de un lead.
 * Valida ownership, normaliza estado, verifica transiciones.
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
    require_once __DIR__ . '/../../utils/crm_status.php';
    require_once __DIR__ . '/../../modelo/crm_lead.php';
    require_once __DIR__ . '/../../modelo/crm_notification.php';
    require_once __DIR__ . '/../../utils/session.php';
    
    // Iniciar sessión para verificar auth híbrida
    start_secure_session();
    
    $userId = 0;
    
    // Estrategia de Autenticación Híbrida (JWT o Sesión Web)
    $headers = getallheaders();
    $authMethod = 'none';

    if (isset($headers['Authorization'])) {
        // Intento 1: JWT
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $auth = new AuthMiddleware();
        $validacion = $auth->validarToken($token);
        
        if ($validacion['success']) {
            $userData = $validacion['data'];
            $userId = (int)$userData['id'];
            $authMethod = 'jwt';
        }
    } 
    
    // Intento 2: Sesión Web (Fallback)
    if ($userId === 0 && isset($_SESSION['registrado']) && $_SESSION['registrado'] === true) {
        $userId = (int)($_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0);
        $authMethod = 'session';
    }

    // Si fallaron ambos
    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado (Token o Sesión requerida)']);
        exit;
    }
    
    // Verificar que es Cliente o Admin
    if (!isUserCliente($userId) && !isUserAdmin($userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado: solo clientes']);
        exit;
    }
    
    // Obtener lead_id desde GET (inyectado por router)
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
    
    // Verificar ownership (solo si no es admin)
    if (!isUserAdmin($userId) && (int)$lead['cliente_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para este lead']);
        exit;
    }
    
    // Leer payload
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['estado'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Campo estado requerido']);
        exit;
    }
    
    // Normalizar estado
    $estadoNuevo = normalizeEstado($data['estado']);
    
    if (!isValidEstado($estadoNuevo)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Estado inválido: {$data['estado']}",
            'estados_validos' => getEstadosValidos()
        ]);
        exit;
    }
    
    // Verificar transición (DESHABILITADO - Cliente puede cambiar a cualquier estado)
    $estadoAnterior = $lead['estado_actual'];
    
    /*
    if (!canTransition($estadoAnterior, $estadoNuevo)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Transición no permitida de {$estadoAnterior} a {$estadoNuevo}"
        ]);
        exit;
    }
    */
    
    $observaciones = $data['observaciones'] ?? null;
    
    // Actualizar estado
    $resultado = CrmLeadModel::actualizarEstado($leadId, $estadoNuevo, $userId, $observaciones);
    
    if (!$resultado['success']) {
        http_response_code(500);
        echo json_encode($resultado);
        exit;
    }
    
    // Crear notificación interna para el proveedor
    CrmNotificationModel::agregar(
        'SEND_TO_PROVIDER',
        $leadId,
        (int)$lead['proveedor_id'],
        [
            'lead_id' => $leadId,
            'proveedor_lead_id' => $lead['proveedor_lead_id'],
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'observaciones' => $observaciones,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    );
    
    // Responder 200 OK
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Estado actualizado a {$estadoNuevo}",
        'estado_anterior' => $estadoAnterior,
        'estado_nuevo' => $estadoNuevo
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
