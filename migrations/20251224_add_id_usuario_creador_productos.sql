-- =====================================================
-- MIGRACIÓN: Sistema de Propiedad de Productos
-- Fecha: 2025-12-24
-- Descripción: Agrega columna id_usuario_creador a productos
--              para control de acceso por proveedor
-- =====================================================

-- =====================================================
-- 1. AGREGAR COLUMNA id_usuario_creador
-- =====================================================

ALTER TABLE productos
    ADD COLUMN IF NOT EXISTS id_usuario_creador INT NULL 
    COMMENT 'ID del usuario que creó este producto (para control de acceso de proveedores)'
    AFTER imagen_url;

-- =====================================================
-- 2. AGREGAR FOREIGN KEY
-- =====================================================

-- Primero verificar si la FK ya existe
SET @fk_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'productos'
    AND CONSTRAINT_NAME = 'fk_producto_usuario_creador'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

-- Solo agregar si no existe (MySQL no soporta IF NOT EXISTS para FK)
-- Si da error de "Duplicate key", ignorar - significa que ya existe
ALTER TABLE productos
    ADD CONSTRAINT fk_producto_usuario_creador
    FOREIGN KEY (id_usuario_creador) REFERENCES usuarios(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- =====================================================
-- 3. CREAR ÍNDICE PARA CONSULTAS EFICIENTES
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_producto_usuario_creador ON productos(id_usuario_creador);

-- =====================================================
-- 4. ACTUALIZAR PRODUCTOS EXISTENTES (OPCIONAL)
-- =====================================================

-- Los productos existentes quedarán con id_usuario_creador = NULL
-- Esto significa que serán visibles para todos (comportamiento retrocompatible)

-- Si quieres asignar todos los productos existentes a un admin específico:
-- UPDATE productos SET id_usuario_creador = 1 WHERE id_usuario_creador IS NULL;

-- =====================================================
-- 5. VERIFICAR CAMBIOS
-- =====================================================

-- Descomentar para verificar:
-- DESCRIBE productos;
-- SELECT id, nombre, id_usuario_creador FROM productos LIMIT 10;

-- =====================================================
-- FIN DE LA MIGRACIÓN
-- =====================================================
