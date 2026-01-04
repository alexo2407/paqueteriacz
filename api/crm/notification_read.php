<?php
/**
 * POST /api/crm/notifications/{id}/read
 * 
 * Marca una notificación como leída.
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
    require_once __DIR__ . '/../../modelo/crm_notification.php';
    require_once __DIR__ . '/../../utils/session.php';
    
    // Auth Híbrida
    start_secure_session();
    $userId = 0;
    $headers = getallheaders();

    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $auth = new AuthMiddleware();
        $validacion = $auth->validarToken($token);
        if ($validacion['success']) $userId = (int)$validacion['data']['id'];
    } 
    
    if ($userId === 0 && isset($_SESSION['registrado']) && $_SESSION['registrado'] === true) {
        $userId = (int)($_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0);
    }

    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    
    // Obtener notification_id desde GET (inyectado por router)
    $notifId = isset($_GET['notification_id']) ? (int)$_GET['notification_id'] : 0;
    
    if ($notifId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'notification_id inválido']);
        exit;
    }
    
    // Marcar como leída
    $result = CrmNotificationModel::marcarLeida($notifId);
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Notificación marcada como leída'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al marcar como leída'
        ]);
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
    error_log("Error en POST notification read: " . $e->getMessage());
}
