<?php
/**
 * GET /api/pedidos/listar
 *
 * Endpoint para obtener el listado extendido de pedidos. Actualmente
 * devuelve un arreglo de pedidos con información adicional (productos,
 * estados, relaciones). No requiere autenticación en esta versión,
 * pero puede protegerse si se desea.
 *
 * Response: { success, message, data }
 */

require_once __DIR__ . '/../../controlador/pedido.php';
require_once __DIR__ . '/../utils/responder.php';

header('Content-Type: application/json');

try {
    $controller = new PedidosController();
    $pedidos = $controller->listarPedidosExtendidos();
    responder(true, 'Listado de pedidos', $pedidos, 200);
} catch (Exception $e) {
    responder(false, 'Error al obtener listado de pedidos: ' . $e->getMessage(), null, 500);
}

?>
