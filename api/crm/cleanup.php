<?php
/**
 * API Endpoint para limpieza de datos CRM
 * Solo accesible para administradores autenticados en sesión
 */

// Iniciar sesión
session_start();

header('Content-Type: application/json');

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['registrado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Verificar que sea admin
require_once __DIR__ . '/../../utils/permissions.php';
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado. Solo administradores.']);
    exit;
}

require_once __DIR__ . '/../../modelo/conexion.php';

// Obtener parámetros
$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? '';
$dryRun = $input['dry_run'] ?? true;

if (empty($type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipo de limpieza no especificado']);
    exit;
}

try {
    $db = (new Conexion())->conectar();
    $startTime = microtime(true);
    $results = [];

    switch ($type) {
        case 'inbox':
            $results = cleanupInbox($db, $dryRun);
            break;
        
        case 'outbox':
            $results = cleanupOutbox($db, $dryRun);
            break;
        
        case 'jobs':
            $results = cleanupJobs($db, $dryRun);
            break;
        
        case 'notifications':
            $results = cleanupNotifications($db, $dryRun);
            break;
        
        case 'all':
            $results = [
                'inbox' => cleanupInbox($db, $dryRun),
                'outbox' => cleanupOutbox($db, $dryRun),
                'jobs' => cleanupJobs($db, $dryRun),
                'notifications' => cleanupNotifications($db, $dryRun)
            ];
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Tipo de limpieza inválido']);
            exit;
    }

    $executionTime = round((microtime(true) - $startTime) * 1000, 2);

    echo json_encode([
        'success' => true,
        'dry_run' => $dryRun,
        'type' => $type,
        'results' => $results,
        'execution_time_ms' => $executionTime
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al ejecutar limpieza: ' . $e->getMessage()
    ]);
    error_log("Error en cleanup.php: " . $e->getMessage());
}


/**
 * Limpiar inbox procesado (>90 días)
 */
function cleanupInbox($db, $dryRun) {
    $query = "SELECT COUNT(*) FROM crm_inbox 
              WHERE status = 'processed' 
              AND processed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    
    $stmt = $db->query($query);
    $count = $stmt->fetchColumn();
    
    if (!$dryRun && $count > 0) {
        $deleteQuery = "DELETE FROM crm_inbox 
                       WHERE status = 'processed' 
                       AND processed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        $db->exec($deleteQuery);
    }
    
    return [
        'table' => 'crm_inbox',
        'description' => 'Mensajes procesados (>90 días)',
        'count' => $count,
        'deleted' => !$dryRun ? $count : 0
    ];
}

/**
 * Limpiar outbox enviado (>90 días)
 */
function cleanupOutbox($db, $dryRun) {
    $query = "SELECT COUNT(*) FROM crm_outbox 
              WHERE status = 'sent' 
              AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
    
    $stmt = $db->query($query);
    $count = $stmt->fetchColumn();
    
    if (!$dryRun && $count > 0) {
        $deleteQuery = "DELETE FROM crm_outbox 
                       WHERE status = 'sent' 
                       AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        $db->exec($deleteQuery);
    }
    
    return [
        'table' => 'crm_outbox',
        'description' => 'Mensajes enviados (>90 días)',
        'count' => $count,
        'deleted' => !$dryRun ? $count : 0
    ];
}

/**
 * Limpiar jobs completados (>30 días)
 */
function cleanupJobs($db, $dryRun) {
    $query = "SELECT COUNT(*) FROM crm_bulk_jobs 
              WHERE status = 'completed' 
              AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    
    $stmt = $db->query($query);
    $count = $stmt->fetchColumn();
    
    if (!$dryRun && $count > 0) {
        $deleteQuery = "DELETE FROM crm_bulk_jobs 
                       WHERE status = 'completed' 
                       AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $db->exec($deleteQuery);
    }
    
    return [
        'table' => 'crm_bulk_jobs',
        'description' => 'Jobs completados (>30 días)',
        'count' => $count,
        'deleted' => !$dryRun ? $count : 0
    ];
}

/**
 * Limpiar notificaciones leídas (>60 días)
 */
function cleanupNotifications($db, $dryRun) {
    $query = "SELECT COUNT(*) FROM crm_notifications 
              WHERE is_read = 1 
              AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)";
    
    $stmt = $db->query($query);
    $count = $stmt->fetchColumn();
    
    if (!$dryRun && $count > 0) {
        $deleteQuery = "DELETE FROM crm_notifications 
                       WHERE is_read = 1 
                       AND created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)";
        $db->exec($deleteQuery);
    }
    
    return [
        'table' => 'crm_notifications',
        'description' => 'Notificaciones leídas (>60 días)',
        'count' => $count,
        'deleted' => !$dryRun ? $count : 0
    ];
}
