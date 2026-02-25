<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../modelo/pedido.php';

    // Iniciar sesión solo si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }

    // Verificar autenticación
    if (empty($_SESSION['registrado'])) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit;
    }

    $clienteId  = !empty($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : null;
    $paisId     = !empty($_GET['pais_id'])    ? (int)$_GET['pais_id']    : null;
    $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
    $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-t');

    $datos = PedidosModel::obtenerEfectividadTemporal($clienteId, $paisId, $fechaDesde, $fechaHasta);

    ob_end_clean();
    echo json_encode($datos);

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
}
