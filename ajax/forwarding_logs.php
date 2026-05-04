<?php
/**
 * AJAX endpoint para consultar logs de forwarding.
 *
 * GET: listar logs con filtros opcionales
 *   - id_provider, status, fecha_desde, fecha_hasta
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../modelo/forwarding.php';

start_secure_session();
header('Content-Type: application/json');

if (empty($_SESSION['registrado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
if (!in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $filtros = [];
    if (!empty($_GET['id_provider']))  $filtros['id_provider']  = $_GET['id_provider'];
    if (!empty($_GET['status']))       $filtros['status']       = $_GET['status'];
    if (!empty($_GET['fecha_desde']))  $filtros['fecha_desde']  = $_GET['fecha_desde'];
    if (!empty($_GET['fecha_hasta']))  $filtros['fecha_hasta']  = $_GET['fecha_hasta'];

    $limit  = isset($_GET['limit'])  ? min((int)$_GET['limit'], 200) : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $logs  = ForwardingModel::obtenerLogs($filtros, $limit, $offset);
    $total = ForwardingModel::contarLogs($filtros);

    echo json_encode([
        'success' => true,
        'data'    => $logs,
        'total'   => $total,
        'limit'   => $limit,
        'offset'  => $offset,
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Método no soportado']);
