<?php
/**
 * Ajax endpoint para DataTables - CRM Leads
 * Usa sesiones PHP, no JWT
 */

// Iniciar sesión
require_once __DIR__ . '/../../../utils/session.php';
start_secure_session();

// Verificar autenticación
if (!isset($_SESSION['registrado'])) {
    http_response_code(401);
    echo json_encode([
        'error' => 'No autenticado',
        'data' => [],
        'recordsTotal' => 0,
        'recordsFiltered' => 0
    ]);
    exit;
}

// Verificar permisos (solo admin)
require_once __DIR__ . '/../../../utils/permissions.php';
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

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../../modelo/conexion.php';
    
    // Parámetros de DataTables
    $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
    $start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
    $searchValue = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
    
    // Filtros personalizados
    $estado = isset($_POST['estado']) ? $_POST['estado'] : '';
    $fechaDesde = isset($_POST['fecha_desde']) ? $_POST['fecha_desde'] : '';
    $fechaHasta = isset($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : '';
    $busqueda = isset($_POST['busqueda']) ? $_POST['busqueda'] : '';
    
    // Conexión a la base de datos
    $db = (new Conexion())->conectar();
    
    $where = [];
    $params = [];
    
    // Filtros
    if (!empty($estado)) {
        $where[] = 'cl.estado_actual = :estado';
        $params[':estado'] = $estado;
    }
    
    if (!empty($fechaDesde)) {
        $where[] = 'cl.fecha_hora >= :fecha_desde';
        $params[':fecha_desde'] = $fechaDesde . ' 00:00:00';
    }
    
    if (!empty($fechaHasta)) {
        $where[] = 'cl.fecha_hora <= :fecha_hasta';
        $params[':fecha_hasta'] = $fechaHasta . ' 23:59:59';
    }
    
    if (!empty($busqueda)) {
        $where[] = '(cl.id LIKE :busqueda OR cl.proveedor_lead_id LIKE :busqueda OR cl.nombre LIKE :busqueda OR cl.telefono LIKE :busqueda)';
        $params[':busqueda'] = '%' . $busqueda . '%';
    }
    
    if (!empty($searchValue)) {
        $where[] = '(cl.id LIKE :search OR cl.proveedor_lead_id LIKE :search OR cl.nombre LIKE :search OR cl.telefono LIKE :search)';
        $params[':search'] = '%' . $searchValue . '%';
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Contar total sin filtros
    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM crm_leads");
    $stmtTotal->execute();
    $recordsTotal = (int)$stmtTotal->fetchColumn();
    
    // Contar con filtros
    $stmtFiltered = $db->prepare("SELECT COUNT(*) FROM crm_leads cl {$whereClause}");
    foreach ($params as $key => $value) {
        $stmtFiltered->bindValue($key, $value);
    }
    $stmtFiltered->execute();
    $recordsFiltered = (int)$stmtFiltered->fetchColumn();
    
    // Obtener datos
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
    
    // Formatear respuesta
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
    
    // Respuesta DataTables
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data
    ]);
    
} catch (Throwable $e) {
    error_log("DataTables Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno: ' . $e->getMessage(),
        'data' => [],
        'recordsTotal' => 0,
        'recordsFiltered' => 0
    ]);
}
