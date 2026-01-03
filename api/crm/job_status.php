<?php
/**
 * GET /api/crm/jobs/{job_id}
 * 
 * Consultar el estado de un job de actualización masiva asíncrona
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
    
    // Obtener job_id desde GET (inyectado por router)
    $jobId = $_GET['job_id'] ?? null;
    
    if (!$jobId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'job_id requerido']);
        exit;
    }
    
    // Buscar job en la base de datos
    $db = (new Conexion())->conectar();
    
    $stmt = $db->prepare("
        SELECT * FROM crm_bulk_jobs 
        WHERE id = :job_id AND user_id = :user_id
    ");
    
    $stmt->execute([
        ':job_id' => $jobId,
        ':user_id' => $userId
    ]);
    
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Job no encontrado o no tienes permiso para verlo'
        ]);
        exit;
    }
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'job_id' => $job['id'],
        'status' => $job['status'],
        'total_leads' => (int)$job['total_leads'],
        'processed_leads' => (int)$job['processed_leads'],
        'successful_leads' => (int)$job['successful_leads'],
        'failed_leads' => (int)$job['failed_leads'],
        'estado' => $job['estado'],
        'created_at' => $job['created_at'],
        'started_at' => $job['started_at'],
        'completed_at' => $job['completed_at']
    ];
    
    // Calcular progreso porcentual
    if ($job['total_leads'] > 0) {
        $response['progress_percent'] = round(
            ($job['processed_leads'] / $job['total_leads']) * 100, 
            2
        );
    }
    
    // Agregar details (successful o failed según corresponda)
    if ($job['failed_details']) {
        $details = json_decode($job['failed_details'], true);
        
        if (isset($details['type']) && isset($details['items'])) {
            // Nuevo formato (type + items)
            if ($details['type'] === 'successful') {
                $response['successful_details'] = $details['items'];
            } else {
                $response['failed_details'] = $details['items'];
            }
        } else {
            // Formato antiguo (solo failed_details)
            $response['failed_details'] = $details;
        }
    }
    
    // Agregar mensaje de error si existe
    if ($job['error_message']) {
        $response['error_message'] = $job['error_message'];
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => $e->getMessage()
    ]);
    error_log("Error consultando job status: " . $e->getMessage());
}
