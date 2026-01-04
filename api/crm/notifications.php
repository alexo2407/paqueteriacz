<?php
/**
 * GET /api/crm/notifications
 * 
 * Obtiene las notificaciones del usuario autenticado.
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
    require_once __DIR__ . '/../../modelo/crm_notification.php';
    
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
    
    // Parámetros opcionales
    $onlyUnread = isset($_GET['unread']) && $_GET['unread'] === 'true';
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    
    // Obtener notificaciones
    $notificaciones = CrmNotificationModel::obtenerPorUsuario($userId, $onlyUnread, $limit);
    $unreadCount = CrmNotificationModel::contarNoLeidas($userId);
    
    // Formatear fechas y parsear payload
    foreach ($notificaciones as &$notif) {
        if ($notif['payload']) {
            $notif['payload'] = json_decode($notif['payload'], true);
        }
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'notifications' => $notificaciones,
        'unread_count' => $unreadCount,
        'total' => count($notificaciones)
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
    error_log("Error en GET notifications: " . $e->getMessage());
}
