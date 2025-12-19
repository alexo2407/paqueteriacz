<?php
/**
 * Apply migration: Add repartidor_updated_at to pedidos table
 */

require_once __DIR__ . '/../modelo/conexion.php';

try {
    $db = (new Conexion())->conectar();
    
    echo "Applying migration: Add repartidor_updated_at to pedidos...\n";
    
    $sql = file_get_contents(__DIR__ . '/20251219_add_repartidor_updated_at.sql');
    $db->exec($sql);
    
    echo "✓ Migration applied successfully!\n";
    echo "Column 'repartidor_updated_at' added to 'pedidos' table.\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
