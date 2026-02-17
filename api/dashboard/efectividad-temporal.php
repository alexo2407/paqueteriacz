<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../modelo/pedido.php';

start_secure_session();

// Verificar autenticación
if (!isset($_SESSION['registrado'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Obtener parámetros
$clienteId = !empty($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : null;
$paisId = !empty($_GET['pais_id']) ? (int)$_GET['pais_id'] : null;
$fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-t');

try {
    $datos = PedidosModel::obtenerEfectividadTemporal($clienteId, $paisId, $fechaDesde, $fechaHasta);
    echo json_encode($datos);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
