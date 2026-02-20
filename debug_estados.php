<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/modelo/conexion.php';

try {
    $db = (new Conexion())->conectar();
    $stmt = $db->query("SELECT id, nombre_estado FROM estados_pedidos ORDER BY id");
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "ID | Nombre\n";
    echo "---|-------\n";
    foreach ($estados as $e) {
        echo $e['id'] . " | " . $e['nombre_estado'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
