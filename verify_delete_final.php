<?php
require_once 'config/config.php';
require_once 'modelo/conexion.php';
require_once 'modelo/producto.php';
require_once 'modelo/pedido.php';
require_once 'modelo/stock.php';

// Helper to get a product
function getTestProduct() {
    $db = (new Conexion())->conectar();
    $stmt = $db->query("SELECT p.id, p.nombre, COALESCE(SUM(s.cantidad), 0) as stock 
                        FROM productos p 
                        LEFT JOIN stock s ON p.id = s.id_producto 
                        GROUP BY p.id 
                        HAVING stock > 10 
                        LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$product = getTestProduct();
if (!$product) { die("No product found.\n"); }

echo "Product: " . $product['nombre'] . " (ID: " . $product['id'] . ")\n";
echo "Initial Stock: " . $product['stock'] . "\n";

// 1. Create
$orderData = [
    'numero_orden' => rand(100000, 999999),
    'destinatario' => 'Verify Delete User',
    'telefono' => '55555555',
    'direccion' => 'Test Dir',
    'latitud' => 0, 'longitud' => 0
];
$items = [['id_producto' => $product['id'], 'cantidad' => 3]];

try {
    $orderId = PedidosModel::crearPedidoConProductos($orderData, $items);
    echo "Order Created (ID: $orderId). Qty: 3\n";
    
    $stockMid = ProductoModel::obtenerStockTotal($product['id']);
    echo "Stock after create: $stockMid (Expected: " . ($product['stock'] - 3) . ")\n";

    // 2. Delete
    echo "Deleting order...\n";
    PedidosModel::eliminarPedido($orderId);
    
    // 3. Verify
    $stockFinal = ProductoModel::obtenerStockTotal($product['id']);
    echo "Stock after delete: $stockFinal (Expected: " . $product['stock'] . ")\n";
    
    $db = (new Conexion())->conectar();
    $stmt = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE id = :id");
    $stmt->execute([':id' => $orderId]);
    $exists = $stmt->fetchColumn();
    
    if ($stockFinal == $product['stock'] && $exists == 0) {
        echo "SUCCESS: Order deleted and stock restored.\n";
    } else {
        echo "FAILURE: Something went wrong.\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
