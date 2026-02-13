<?php
require_once __DIR__ . '/modelo/conexion.php';

try {
    $db = (new Conexion())->conectar();
    $stmt = $db->query("DESCRIBE pedidos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        if ($col['Field'] === 'numero_orden') {
            print_r($col);
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
