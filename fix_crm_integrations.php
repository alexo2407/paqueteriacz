<?php
require_once 'modelo/conexion.php';

try {
    $db = (new Conexion())->conectar();
    $db->exec('ALTER TABLE crm_integrations ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST');
    echo "Column id added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
