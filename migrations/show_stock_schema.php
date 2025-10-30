<?php
require_once __DIR__ . '/../config/config.php';
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_SCHEMA);
    $db = new PDO($dsn, DB_USER, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $db->query("SHOW FULL COLUMNS FROM stock");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo sprintf("%s | %s | Null=%s | Default=%s | Extra=%s\n", $c['Field'], $c['Type'], $c['Null'], var_export($c['Default'], true), $c['Extra']);
    }
} catch (PDOException $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
