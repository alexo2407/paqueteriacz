<?php
/**
 * GET /api/productos/listar
 *
 * Devuelve la lista de productos con inventario agregado (stock_total).
 * Respuesta: { success, message, data }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../modelo/producto.php';
require_once __DIR__ . '/../utils/responder.php';

try {
    $productos = ProductoModel::listarConInventario();

    // Si se solicita detalle de stock, anexar movimientos de stock por producto
    $includeStock = isset($_GET['include_stock']) && ($_GET['include_stock'] === '1' || strtolower($_GET['include_stock']) === 'true');
    if ($includeStock && is_array($productos)) {
        foreach ($productos as &$p) {
            $pid = isset($p['id']) ? (int)$p['id'] : null;
            if ($pid !== null) {
                $p['stock_entries'] = ProductoModel::listarStockPorProducto($pid);
            } else {
                $p['stock_entries'] = [];
            }
        }
        unset($p);
    }

    responder(true, 'Listado de productos', $productos, 200);
} catch (Exception $e) {
    responder(false, 'Error al listar productos: ' . $e->getMessage(), null, 500);
}

?>
