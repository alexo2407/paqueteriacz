<?php
require_once __DIR__ . '/../config/config.php';
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_SCHEMA);
    $db = new PDO($dsn, DB_USER, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $userId = 3; // vendedor usado en el test
    $stmt = $db->prepare('SELECT * FROM stock WHERE id_usuario = :uid ORDER BY id DESC LIMIT 20');
    $stmt->execute([':uid' => $userId]);
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        echo "No se encontraron filas de stock para id_usuario={$userId}.\n";
        exit(0);
    }
    echo "Movimientos en stock para id_usuario={$userId}:\n";
    foreach ($rows as $r) echo json_encode($r) . "\n";

} catch (PDOException $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
