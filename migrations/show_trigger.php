<?php
require_once __DIR__ . '/../config/config.php';
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_SCHEMA);
    $db = new PDO($dsn, DB_USER, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $trigger = 'descontar_stock_al_insertar_producto';
    $stmt = $db->query("SHOW CREATE TRIGGER `{$trigger}`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "Trigger '{$trigger}' no encontrado.\n";
        exit(0);
    }
    echo "Trigger '{$trigger}':\n";
    print_r($row);
} catch (PDOException $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
