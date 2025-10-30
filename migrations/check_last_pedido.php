<?php
require_once __DIR__ . '/../config/config.php';
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_SCHEMA);
    $db = new PDO($dsn, DB_USER, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    // Obtener el último pedido insertado
    $pstmt = $db->query('SELECT id, numero_orden, id_vendedor, id_proveedor, fecha_ingreso FROM pedidos ORDER BY id DESC LIMIT 1');
    $pedido = $pstmt->fetch();
    if (!$pedido) {
        echo "No hay pedidos en la base de datos.\n";
        exit(0);
    }
    echo "Último pedido:\n" . json_encode($pedido) . "\n";

    // Obtener productos asociados
    $dstmt = $db->prepare('SELECT * FROM pedidos_productos WHERE id_pedido = :pid');
    $dstmt->execute([':pid' => $pedido['id']]);
    $productos = $dstmt->fetchAll();
    echo "Productos del pedido:\n";
    foreach ($productos as $p) echo json_encode($p) . "\n";

} catch (PDOException $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
