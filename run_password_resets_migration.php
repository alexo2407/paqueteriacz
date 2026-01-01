<?php
/**
 * Script temporal para ejecutar la migración: create_password_resets_table
 * Fecha: 2025-12-20
 */

require_once __DIR__ . '/modelo/conexion.php';

echo "==============================================\n";
echo "Ejecutando migración: password_resets table\n";
echo "==============================================\n\n";

try {
    $db = (new Conexion())->conectar();
    
    // Verificar si la tabla ya existe
    echo "1. Verificando si la tabla 'password_resets' ya existe...\n";
    $stmt = $db->query("SHOW TABLES LIKE 'password_resets'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "   ⚠️  La tabla 'password_resets' ya existe.\n";
        echo "   ¿Deseas continuar de todas formas? (la migración usa CREATE TABLE IF NOT EXISTS)\n";
        echo "   Continuando...\n\n";
    } else {
        echo "   ✓ La tabla no existe. Procediendo con la creación.\n\n";
    }
    
    // Leer y ejecutar el archivo SQL
    echo "2. Leyendo archivo de migración...\n";
    $sqlFile = __DIR__ . '/migrations/create_password_resets_table.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Archivo de migración no encontrado: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    echo "   ✓ Archivo leído correctamente.\n\n";
    
    // Ejecutar la migración
    echo "3. Ejecutando migración...\n";
    $db->exec($sql);
    echo "   ✓ Migración ejecutada exitosamente!\n\n";
    
    // Verificar la estructura de la tabla creada
    echo "4. Verificando estructura de la tabla creada:\n";
    $stmt = $db->query("DESCRIBE password_resets");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n   Columnas creadas:\n";
    echo "   " . str_repeat("-", 80) . "\n";
    printf("   %-25s %-20s %-10s %-10s\n", "Campo", "Tipo", "Null", "Key");
    echo "   " . str_repeat("-", 80) . "\n";
    
    foreach ($columns as $col) {
        printf("   %-25s %-20s %-10s %-10s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'], 
            $col['Key']
        );
    }
    echo "   " . str_repeat("-", 80) . "\n\n";
    
    // Verificar índices
    echo "5. Verificando índices creados:\n";
    $stmt = $db->query("SHOW INDEX FROM password_resets");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n   Índices:\n";
    echo "   " . str_repeat("-", 80) . "\n";
    printf("   %-20s %-20s %-15s\n", "Nombre del Índice", "Columna", "Único");
    echo "   " . str_repeat("-", 80) . "\n";
    
    foreach ($indexes as $idx) {
        printf("   %-20s %-20s %-15s\n", 
            $idx['Key_name'], 
            $idx['Column_name'], 
            $idx['Non_unique'] == 0 ? 'Sí' : 'No'
        );
    }
    echo "   " . str_repeat("-", 80) . "\n\n";
    
    echo "==============================================\n";
    echo "✅ MIGRACIÓN COMPLETADA EXITOSAMENTE\n";
    echo "==============================================\n";
    echo "\nLa tabla 'password_resets' está lista para usar.\n";
    echo "Puedes eliminar este script si lo deseas.\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERROR DE BASE DE DATOS:\n";
    echo "   " . $e->getMessage() . "\n\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERROR:\n";
    echo "   " . $e->getMessage() . "\n\n";
    exit(1);
}
