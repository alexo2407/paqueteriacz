<?php
/**
 * Script para generar SKUs automáticos para productos sin SKU
 * 
 * Este script ejecuta la migración SQL para generar SKUs
 * para todos los productos que no tienen uno asignado.
 * 
 * Uso: php migraciones/ejecutar_generar_skus.php
 */

require_once __DIR__ . '/../modelo/conexion.php';

try {
    // Conectar a la base de datos usando la clase Conexion existente
    $pdo = (new Conexion())->conectar();
    
    echo "=================================================\n";
    echo "   GENERACIÓN AUTOMÁTICA DE SKUs FALTANTES\n";
    echo "=================================================\n\n";
    
    // Verificar productos sin SKU antes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE sku IS NULL OR sku = ''");
    $antes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "📊 Productos sin SKU: $antes\n\n";
    
    if ($antes == 0) {
        echo "✅ Todos los productos ya tienen SKU asignado.\n";
        echo "   No se requiere ninguna actualización.\n\n";
        exit(0);
    }
    
    echo "🔄 Generando SKUs...\n\n";
    
    // Verificar si la tabla categorias existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'categorias'");
    $categoriaExists = $stmt->rowCount() > 0;
    
    if ($categoriaExists) {
        // Actualizar productos con categoría
        $sql1 = "
            UPDATE productos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            SET p.sku = CONCAT(
                UPPER(SUBSTRING(COALESCE(c.nombre, 'PROD'), 1, 4)),
                '-',
                LPAD(p.id, 3, '0')
            )
            WHERE (p.sku IS NULL OR p.sku = '')
            AND p.categoria_id IS NOT NULL
        ";
        
        $stmt1 = $pdo->exec($sql1);
        echo "   ✓ Productos con categoría actualizados: $stmt1\n";
    } else {
        echo "   ⚠  Tabla 'categorias' no encontrada, usando prefijo genérico\n";
        $stmt1 = 0;
    }
    
    // Actualizar productos sin categoría (o todos si no existe tabla categorias)
    if ($categoriaExists) {
        $sql2 = "
            UPDATE productos
            SET sku = CONCAT('PROD-', LPAD(id, 3, '0'))
            WHERE (sku IS NULL OR sku = '')
            AND categoria_id IS NULL
        ";
    } else {
        // Si no existe categorias, actualizar todos con PROD
        $sql2 = "
            UPDATE productos
            SET sku = CONCAT('PROD-', LPAD(id, 3, '0'))
            WHERE (sku IS NULL OR sku = '')
        ";
    }
    
    $stmt2 = $pdo->exec($sql2);
    echo "   ✓ Productos sin categoría actualizados: $stmt2\n\n";
    
    // Verificar productos sin SKU después
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE sku IS NULL OR sku = ''");
    $despues = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo "📊 Resumen:\n";
    echo "   • Productos sin SKU antes: $antes\n";
    echo "   • Productos actualizados: " . ($antes - $despues) . "\n";
    echo "   • Productos sin SKU después: $despues\n\n";
    
    if ($despues == 0) {
        echo "✅ ¡Éxito! Todos los productos ahora tienen SKU.\n\n";
        
        // Mostrar algunos ejemplos
        echo "📋 Ejemplos de SKUs generados:\n";
        
        if ($categoriaExists) {
            $stmt = $pdo->query("
                SELECT p.id, p.nombre, p.sku, c.nombre as categoria
                FROM productos p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                ORDER BY p.id DESC
                LIMIT 5
            ");
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $cat = $row['categoria'] ?? 'Sin categoría';
                echo sprintf(
                    "   • %-15s | %-30s | %s\n",
                    $row['sku'],
                    substr($row['nombre'], 0, 30),
                    $cat
                );
            }
        } else {
            $stmt = $pdo->query("
                SELECT id, nombre, sku
                FROM productos
                ORDER BY id DESC
                LIMIT 5
            ");
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo sprintf(
                    "   • %-15s | %-30s\n",
                    $row['sku'],
                    substr($row['nombre'], 0, 30)
                );
            }
        }
        echo "\n";
    } else {
        echo "⚠️  Advertencia: Aún quedan $despues productos sin SKU.\n";
        echo "   Por favor, revisa estos productos manualmente.\n\n";
    }
    
    echo "=================================================\n";
    echo "   Migración completada\n";
    echo "=================================================\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}
