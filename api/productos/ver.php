<?php
/**
 * GET /api/productos/ver?id=<id>
 *
 * Devuelve un producto por id y sus movimientos de stock (stock_entries).
 * Response: { success, message, data }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../modelo/producto.php';
require_once __DIR__ . '/../utils/responder.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    responder(false, 'ID de producto inválido', null, 400);
    exit;
}

try {
    $producto = ProductoModel::obtenerPorId($id);
    if (!$producto) {
        responder(false, 'Producto no encontrado', null, 404);
        exit;
    }

    // Añadir movimientos de stock
    $stockEntries = ProductoModel::listarStockPorProducto($id);
    $producto['stock_entries'] = $stockEntries;

    responder(true, 'Producto encontrado', $producto, 200);
} catch (Exception $e) {
    responder(false, 'Error al obtener producto: ' . $e->getMessage(), null, 500);
}

?>
