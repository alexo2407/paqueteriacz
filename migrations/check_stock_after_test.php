<?php
require_once __DIR__ . '/../config/config.php';

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_SCHEMA);
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $db = new PDO($dsn, DB_USER, DB_PASSWORD, $opts);

    $productId = 3; // ajustar si es necesario
    $stmt = $db->prepare('SELECT * FROM stock WHERE id_producto = :pid ORDER BY id DESC LIMIT 10');
    $stmt->execute([':pid' => $productId]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        echo "No se encontraron movimientos recientes en stock para el producto {$productId}.\n";
        exit(0);
    }

    echo "Últimos movimientos en stock (producto {$productId}):\n";
    foreach ($rows as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, "Error consultando stock: " . $e->getMessage() . "\n");
    exit(1);
}
