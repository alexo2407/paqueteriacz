<?php
/**
 * API: Obtener pedidos en estado 'En bodega' (ID 1) para el Módulo de Etiquetas Térmicas
 * GET/POST: /api/pedidos/etiquetas_bodega.php
 */

ob_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../utils/session.php';
require_once __DIR__ . '/../../utils/permissions.php';
require_once __DIR__ . '/../../modelo/pedido.php';

if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('start_secure_session')) start_secure_session();
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0);
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
$sessionRol = $_SESSION['rol'] ?? null;
$sessionRoles = $_SESSION['roles'] ?? [];

$isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true)
    || in_array('Administrador', $rolesNombres, true)
    || in_array('admin', $rolesNombres, true)
    || (function_exists('isAdmin') && isAdmin())
    || ($sessionRol == 1)
    || (is_array($sessionRoles) && in_array(1, $sessionRoles));

$isCliente = in_array(ROL_NOMBRE_CLIENTE, $rolesNombres, true)
    || in_array('Cliente', $rolesNombres, true)
    || in_array(ROL_NOMBRE_PROVEEDOR, $rolesNombres, true)
    || in_array('Proveedor', $rolesNombres, true)
    || in_array($sessionRol, [4, 5])
    || (function_exists('isCliente') && isCliente())
    || (function_exists('isProveedor') && isProveedor());

if (empty($userId) || (!$isAdmin && !$isCliente)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No autorizado. Solo Administradores y Clientes pueden acceder a este módulo.']);
    exit;
}

try {
    $filtros = [
        'fecha_desde' => !empty($_REQUEST['fecha_desde']) ? trim($_REQUEST['fecha_desde']) : null,
        'fecha_hasta' => !empty($_REQUEST['fecha_hasta']) ? trim($_REQUEST['fecha_hasta']) : null,
        'tipo_fecha'  => !empty($_REQUEST['tipo_fecha']) ? trim($_REQUEST['tipo_fecha']) : 'fecha_entrega',
        'search'      => !empty($_REQUEST['search']) ? trim($_REQUEST['search']) : null,
        'id_cliente'  => ($isAdmin && !empty($_REQUEST['id_cliente'])) ? (int)$_REQUEST['id_cliente'] : null,
    ];

    $pedidos = PedidosModel::obtenerPedidosParaEtiquetas([], $filtros, $userId, $isAdmin);

    echo json_encode([
        'success' => true,
        'total'   => count($pedidos),
        'data'    => $pedidos,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno al consultar pedidos en bodega: ' . $e->getMessage()
    ]);
}
