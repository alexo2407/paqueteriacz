<?php
require_once 'config/config.php';
require_once 'modelo/conexion.php';

try {
    $db = (new Conexion())->conectar();
    
    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM pedidos LIKE 'fecha_entrega'");
    if ($stmt->rowCount() == 0) {
        echo "Adding 'fecha_entrega' column...\n";
        $db->exec("ALTER TABLE pedidos ADD COLUMN fecha_entrega DATE NULL AFTER id_estado");
        echo "Column added successfully.\n";
    } else {
        echo "Column 'fecha_entrega' already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
