<?php
/**
 * Script para ejecutar migraciones de Auditoría y Propiedad de Productos
 * Ejecutar desde la línea de comandos:
 * php migrations/run_audit_migrations.php
 */

require_once __DIR__ . '/../modelo/conexion.php';

echo "===========================================\n";
echo "  EJECUTANDO MIGRACIONES\n";
echo "  Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n\n";

try {
    $db = (new Conexion())->conectar();
    echo "✅ Conexión a base de datos exitosa\n\n";
    
    // Migración 1: Sistema de Auditoría
    echo "--- Migración 1: Sistema de Auditoría ---\n";
    
    $sqlAuditoria = "
    CREATE TABLE IF NOT EXISTS auditoria_cambios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tabla VARCHAR(50) NOT NULL COMMENT 'Nombre de la tabla afectada',
        id_registro INT NOT NULL COMMENT 'ID del registro modificado',
        accion ENUM('crear', 'actualizar', 'eliminar') NOT NULL COMMENT 'Tipo de operación',
        id_usuario INT NULL COMMENT 'Usuario que realizó la acción',
        datos_anteriores JSON NULL COMMENT 'Estado anterior del registro',
        datos_nuevos JSON NULL COMMENT 'Estado nuevo del registro',
        ip_address VARCHAR(45) NULL COMMENT 'Dirección IP del cliente',
        user_agent VARCHAR(500) NULL COMMENT 'User agent del navegador',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha del cambio',
        
        INDEX idx_auditoria_tabla (tabla),
        INDEX idx_auditoria_registro (tabla, id_registro),
        INDEX idx_auditoria_usuario (id_usuario),
        INDEX idx_auditoria_accion (accion),
        INDEX idx_auditoria_fecha (created_at),
        INDEX idx_auditoria_tabla_fecha (tabla, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Registro centralizado de cambios en tablas maestras'
    ";
    
    $db->exec($sqlAuditoria);
    echo "✅ Tabla 'auditoria_cambios' creada/verificada\n";
    
    // Agregar foreign key si no existe
    try {
        $db->exec("ALTER TABLE auditoria_cambios ADD CONSTRAINT fk_auditoria_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "✅ Foreign key 'fk_auditoria_usuario' creada\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "ℹ️  Foreign key ya existe, continuando...\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n--- Migración 2: Propiedad de Productos ---\n";
    
    // Verificar si la columna ya existe
    $stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'id_usuario_creador'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE productos ADD COLUMN id_usuario_creador INT NULL COMMENT 'ID del usuario creador' AFTER imagen_url");
        echo "✅ Columna 'id_usuario_creador' agregada a 'productos'\n";
    } else {
        echo "ℹ️  Columna 'id_usuario_creador' ya existe, continuando...\n";
    }
    
    // Agregar foreign key
    try {
        $db->exec("ALTER TABLE productos ADD CONSTRAINT fk_producto_usuario_creador FOREIGN KEY (id_usuario_creador) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "✅ Foreign key 'fk_producto_usuario_creador' creada\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "ℹ️  Foreign key ya existe, continuando...\n";
        } else {
            throw $e;
        }
    }
    
    // Crear índice
    try {
        $db->exec("CREATE INDEX idx_producto_usuario_creador ON productos(id_usuario_creador)");
        echo "✅ Índice 'idx_producto_usuario_creador' creado\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "ℹ️  Índice ya existe, continuando...\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n===========================================\n";
    echo "  ✅ MIGRACIONES COMPLETADAS EXITOSAMENTE\n";
    echo "===========================================\n\n";
    
    // Verificar estructuras
    echo "--- Verificación de estructuras ---\n\n";
    
    echo "Tabla 'auditoria_cambios':\n";
    $stmt = $db->query("DESCRIBE auditoria_cambios");
    foreach ($stmt->fetchAll() as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\nColumna 'id_usuario_creador' en 'productos':\n";
    $stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'id_usuario_creador'");
    $col = $stmt->fetch();
    if ($col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n✅ Todo listo!\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
