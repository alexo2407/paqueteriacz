<?php
/**
 * Migración: Crear tabla crm_notifications
 * Ejecutar desde CLI: php sql/migrate_notifications.php
 */

require_once __DIR__ . '/../modelo/conexion.php';

echo "========================================\n";
echo "Migración: Crear tabla crm_notifications\n";
echo "========================================\n\n";

try {
    $db = (new Conexion())->conectar();
    
    echo "✓ Conexión a la base de datos establecida.\n\n";
    
    // Leer el archivo SQL
    $sqlFile = __DIR__ . '/create_crm_notifications.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Archivo SQL no encontrado: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Dividir por punto y coma para ejecutar múltiples queries
    $statements = explode(';', $sql);
    
    $executedCount = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Saltar comentarios y líneas vacías
        if (empty($statement) || 
            strpos($statement, '--') === 0 || 
            strpos($statement, '/*') === 0) {
            continue;
        }
        
        // Saltar bloques comentados con /* */
        if (strpos($statement, '/*') !== false) {
            continue;
        }
        
        try {
            $db->exec($statement);
            $executedCount++;
            
            // Mostrar progreso para CREATE TABLE y DESCRIBE
            if (stripos($statement, 'CREATE TABLE') !== false) {
                echo "✓ Tabla crm_notifications creada exitosamente.\n";
            } elseif (stripos($statement, 'DESCRIBE') !== false) {
                echo "\n--- Estructura de la tabla ---\n";
                $result = $db->query($statement);
                $columns = $result->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($columns as $col) {
                    printf("  %-20s %-20s %s\n", 
                        $col['Field'], 
                        $col['Type'], 
                        $col['Key'] ? "[{$col['Key']}]" : ''
                    );
                }
                echo "------------------------------\n\n";
            } elseif (stripos($statement, 'SELECT COUNT') !== false) {
                $result = $db->query($statement);
                $count = $result->fetchColumn();
                echo "✓ Total de notificaciones: $count\n\n";
            } elseif (stripos($statement, 'SHOW TABLES') !== false) {
                echo "--- Tablas CRM en la base de datos ---\n";
                $result = $db->query($statement);
                $tables = $result->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($tables as $table) {
                    echo "  • $table\n";
                }
                echo "--------------------------------------\n\n";
            }
            
        } catch (PDOException $e) {
            // Si es error de tabla ya existe, es OK
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠ Tabla crm_notifications ya existe (OK).\n";
            } else {
                throw $e;
            }
        }
    }
    
    echo "\n========================================\n";
    echo "✓ Migración completada exitosamente.\n";
    echo "  Statements ejecutados: $executedCount\n";
    echo "========================================\n\n";
    
    // Verificar que la tabla existe
    $stmt = $db->query("SHOW TABLES LIKE 'crm_notifications'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✓ Verificación: La tabla crm_notifications existe.\n\n";
        
        // Mostrar contadores
        $stmt = $db->query("SELECT COUNT(*) FROM crm_notifications");
        $count = $stmt->fetchColumn();
        echo "  Total de registros: $count\n\n";
    } else {
        echo "✗ Error: La tabla crm_notifications NO existe.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "  Archivo: " . $e->getFile() . "\n";
    echo "  Línea: " . $e->getLine() . "\n\n";
    exit(1);
}
