<?php
/**
 * Endpoint directo para cambiar estado de leads desde notificaciones
 * Usa SOLO sesión PHP (sin JWT, sin router API)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../utils/session.php';
require_once __DIR__ . '/../../utils/crm_roles.php';
require_once __DIR__ . '/../../utils/crm_status.php';
require_once __DIR__ . '/../../modelo/crm_lead.php';
require_once __DIR__ . '/../../modelo/crm_notification.php';

header('Content-Type: application/json');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    // Iniciar sesión
    start_secure_session();
    
    // Verificar autenticación
    if (empty($_SESSION['registrado'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    
    $userId = (int)($_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0);
    
    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario inválido']);
        exit;
    }
    
    // Verificar que es Cliente CRM o Admin
    if (!isClienteCRM($userId) && !isUserAdmin($userId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado: solo clientes']);
        exit;
    }
    
    // Obtener datos del POST
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['lead_id']) || !isset($data['estado'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }
    
    $leadId = (int)$data['lead_id'];
    $estadoNuevo = normalizeEstado($data['estado']);
    
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
    
    $estadoAnterior = $lead['estado_actual'];
    $observaciones = $data['observaciones'] ?? 'Cambio rápido desde notificaciones';
    
    // Actualizar estado
    $resultado = CrmLeadModel::actualizarEstado($leadId, $estadoNuevo, $userId, $observaciones);
    
    if (!$resultado['success']) {
        http_response_code(500);
        echo json_encode($resultado);
        exit;
    }
    
    
    // Crear notificación para el cliente (quien hizo el cambio)
    // Esto permitirá que vea la actualización en su tab "Actualizaciones"
    CrmNotificationModel::agregar(
        'SEND_TO_CLIENT',  // Aunque es el cliente quien lo hizo, usamos este tipo para consistencia
        $leadId,
        $userId,  // El cliente mismo
        [
            'lead_id' => $leadId,
            'proveedor_lead_id' => $lead['proveedor_lead_id'] ?? '',
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'observaciones' => $observaciones,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s')
        ]
    );
    
    // Crear notificación para el proveedor (si existe)
    if (isset($lead['proveedor_id']) && $lead['proveedor_id'] > 0) {
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
    }
    
    // Responder éxito
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Estado actualizado a {$estadoNuevo}",
        'estado_anterior' => $estadoAnterior,
        'estado_nuevo' => $estadoNuevo
    ]);
    
} catch (Throwable $e) {
    error_log("Error in quick_status_change.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
}
