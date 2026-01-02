<?php
/**
 * POST /api/crm/leads/datatable
 * 
 * Endpoint para DataTables server-side processing.
 * Requiere autenticación de sesión (no JWT).
 */

// Iniciar sesión si no está iniciada
require_once __DIR__ . '/../../../controlador/inicio.php';
start_secure_session();

header('Content-Type: application/json');

try {
    // Log para debugging
    error_log("DataTables endpoint called - Session ID: " . session_id());
    error_log("Session registrado: " . (isset($_SESSION['registrado']) ? 'YES' : 'NO'));
    error_log("Session data: " . json_encode($_SESSION));
    
    // Verificar sesión activa
    if (!isset($_SESSION['registrado'])) {
        error_log("DataTables: No authenticated session found");
        http_response_code(401);
        echo json_encode([
            'error' => 'No autenticado',
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0
        ]);
        exit;
    }
    
    require_once __DIR__ . '/../../../utils/permissions.php';
    require_once __DIR__ . '/../../../modelo/crm_lead.php';
    require_once __DIR__ . '/../../../modelo/conexion.php';
    
    $userId = (int)$_SESSION['id'];
    
    // Verificar permisos (solo admin puede ver todos los leads)
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Acceso denegado',
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0
        ]);
        exit;
    }
    
    // Obtener parámetros de DataTables
    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
    $start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
    
    // Filtros personalizados
    $estado = isset($_POST['estado']) ? $_POST['estado'] : '';
    $fechaDesde = isset($_POST['fecha_desde']) ? $_POST['fecha_desde'] : '';
    $fechaHasta = isset($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : '';
    $busqueda = isset($_POST['busqueda']) ? $_POST['busqueda'] : '';
    
    // Construir consulta
    $db = (new Conexion())->conectar();
    
    $where = [];
    $params = [];
    
    // Filtro de estado
    if (!empty($estado)) {
        $where[] = 'cl.estado_actual = :estado';
        $params[':estado'] = $estado;
    }
    
    // Filtro de fecha desde
    if (!empty($fechaDesde)) {
        $where[] = 'cl.fecha_hora >= :fecha_desde';
        $params[':fecha_desde'] = $fechaDesde . ' 00:00:00';
    }
    
    // Filtro de fecha hasta
    if (!empty($fechaHasta)) {
        $where[] = 'cl.fecha_hora <= :fecha_hasta';
        $params[':fecha_hasta'] = $fechaHasta . ' 23:59:59';
    }
    
    // Búsqueda general
    if (!empty($busqueda)) {
        $where[] = '(cl.id LIKE :busqueda OR cl.proveedor_lead_id LIKE :busqueda OR cl.nombre LIKE :busqueda OR cl.telefono LIKE :busqueda)';
        $params[':busqueda'] = '%' . $busqueda . '%';
    }
    
    // Búsqueda de DataTables
    if (!empty($searchValue)) {
        $where[] = '(cl.id LIKE :search OR cl.proveedor_lead_id LIKE :search OR cl.nombre LIKE :search OR cl.telefono LIKE :search)';
        $params[':search'] = '%' . $searchValue . '%';
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Contar total de registros (sin filtros)
    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM crm_leads");
    $stmtTotal->execute();
    $recordsTotal = (int)$stmtTotal->fetchColumn();
    
    // Contar registros filtrados
    $stmtFiltered = $db->prepare("SELECT COUNT(*) FROM crm_leads cl {$whereClause}");
    foreach ($params as $key => $value) {
        $stmtFiltered->bindValue($key, $value);
    }
    $stmtFiltered->execute();
    $recordsFiltered = (int)$stmtFiltered->fetchColumn();
    
    // Obtener registros con paginación y JOINs para nombres
    $sql = "
        SELECT 
            cl.*,
            up.nombre as proveedor_nombre,
            uc.nombre as cliente_nombre
        FROM crm_leads cl
        LEFT JOIN usuarios up ON cl.proveedor_id = up.id
        LEFT JOIN usuarios uc ON cl.cliente_id = uc.id
        {$whereClause}
        ORDER BY cl.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
    $stmt->execute();
    
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear respuesta para DataTables
    $data = [];
    foreach ($leads as $lead) {
        $data[] = [
            'id' => $lead['id'],
            'proveedor_lead_id' => $lead['proveedor_lead_id'] ?? 'N/A',
            'nombre' => $lead['nombre'] ?? 'N/A',
            'telefono' => $lead['telefono'] ?? 'N/A',
            'estado_actual' => $lead['estado_actual'] ?? 'EN_ESPERA',
            'proveedor_nombre' => $lead['proveedor_nombre'] ?? 'N/A',
            'cliente_nombre' => $lead['cliente_nombre'] ?? 'N/A',
            'created_at' => $lead['created_at']
        ];
    }
    
    // Respuesta en formato DataTables
    http_response_code(200);
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data
    ]);
    
} catch (Throwable $e) {
    error_log("DataTables CRM Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor: ' . $e->getMessage(),
        'data' => [],
        'recordsTotal' => 0,
        'recordsFiltered' => 0
    ]);
}
