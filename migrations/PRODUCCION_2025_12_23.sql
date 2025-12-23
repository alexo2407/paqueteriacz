-- =====================================================
-- SCRIPT DE MIGRACIÓN PARA PRODUCCIÓN
-- Fecha: 2025-12-23
-- Base de datos: sistema_multinacional
-- =====================================================
-- INSTRUCCIONES:
-- 1. Hacer BACKUP de la base de datos antes de ejecutar
-- 2. Ejecutar este script en orden
-- 3. Verificar que no haya errores
-- =====================================================

-- =====================================================
-- 1. CREAR TABLA DE RECUPERACIÓN DE CONTRASEÑAS
-- =====================================================

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. CREAR TABLA DE CATEGORÍAS DE PRODUCTOS
-- =====================================================

CREATE TABLE IF NOT EXISTS categorias_productos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    padre_id INT NULL COMMENT 'Para categorías anidadas',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (padre_id) REFERENCES categorias_productos(id) ON DELETE SET NULL,
    INDEX idx_categoria_activa (activo),
    INDEX idx_categoria_padre (padre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar categorías básicas
INSERT IGNORE INTO categorias_productos (nombre, descripcion, padre_id, activo) VALUES
('Electrónica', 'Productos electrónicos y tecnología', NULL, TRUE),
('Ropa', 'Prendas de vestir y accesorios', NULL, TRUE),
('Alimentos', 'Productos alimenticios', NULL, TRUE),
('Hogar', 'Artículos para el hogar', NULL, TRUE),
('Otros', 'Productos sin categoría específica', NULL, TRUE);

-- =====================================================
-- 3. AGREGAR CAMPOS A TABLA PRODUCTOS
-- =====================================================
-- NOTA: Ejecutar solo si las columnas NO existen

-- Verificar y agregar columnas (ejecutar una por una si hay errores)
ALTER TABLE productos 
    ADD COLUMN IF NOT EXISTS sku VARCHAR(100) UNIQUE AFTER id,
    ADD COLUMN IF NOT EXISTS categoria_id INT AFTER sku,
    ADD COLUMN IF NOT EXISTS marca VARCHAR(100) AFTER categoria_id,
    ADD COLUMN IF NOT EXISTS unidad_medida ENUM('unidad', 'kg', 'litro', 'metro', 'caja', 'paquete', 'docena') DEFAULT 'unidad' AFTER marca,
    ADD COLUMN IF NOT EXISTS stock_minimo INT DEFAULT 10 AFTER unidad_medida,
    ADD COLUMN IF NOT EXISTS stock_maximo INT DEFAULT 100 AFTER stock_minimo,
    ADD COLUMN IF NOT EXISTS activo BOOLEAN DEFAULT TRUE AFTER stock_maximo,
    ADD COLUMN IF NOT EXISTS imagen_url VARCHAR(500) AFTER activo;

-- Agregar timestamps si no existen
ALTER TABLE productos 
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Agregar foreign key (ignorar si ya existe)
-- ALTER TABLE productos ADD CONSTRAINT fk_producto_categoria 
--     FOREIGN KEY (categoria_id) REFERENCES categorias_productos(id) 
--     ON DELETE SET NULL;

-- Índices para productos
CREATE INDEX IF NOT EXISTS idx_producto_categoria ON productos(categoria_id);
CREATE INDEX IF NOT EXISTS idx_producto_activo ON productos(activo);
CREATE INDEX IF NOT EXISTS idx_producto_sku ON productos(sku);
CREATE INDEX IF NOT EXISTS idx_producto_marca ON productos(marca);

-- Actualizar productos existentes
UPDATE productos SET activo = TRUE WHERE activo IS NULL;

-- =====================================================
-- 4. AGREGAR CAMPOS A TABLA STOCK
-- =====================================================
-- NOTA: Ejecutar solo si las columnas NO existen

ALTER TABLE stock
    ADD COLUMN IF NOT EXISTS tipo_movimiento ENUM('entrada', 'salida', 'ajuste', 'devolucion', 'transferencia') NOT NULL DEFAULT 'entrada' AFTER cantidad,
    ADD COLUMN IF NOT EXISTS referencia_tipo ENUM('pedido', 'compra', 'ajuste_manual', 'devolucion', 'transferencia') NULL AFTER tipo_movimiento,
    ADD COLUMN IF NOT EXISTS referencia_id INT NULL AFTER referencia_tipo,
    ADD COLUMN IF NOT EXISTS motivo VARCHAR(255) AFTER referencia_id,
    ADD COLUMN IF NOT EXISTS ubicacion_origen VARCHAR(100) AFTER motivo,
    ADD COLUMN IF NOT EXISTS ubicacion_destino VARCHAR(100) DEFAULT 'Principal' AFTER ubicacion_origen,
    ADD COLUMN IF NOT EXISTS costo_unitario DECIMAL(10,2) AFTER ubicacion_destino,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER costo_unitario;

-- Actualizar registros existentes de stock
UPDATE stock 
SET tipo_movimiento = CASE 
    WHEN cantidad > 0 THEN 'entrada'
    WHEN cantidad < 0 THEN 'salida'
    ELSE 'ajuste'
END,
ubicacion_destino = COALESCE(ubicacion_destino, 'Principal'),
created_at = COALESCE(created_at, updated_at, NOW())
WHERE tipo_movimiento = 'entrada' AND created_at IS NULL;

-- Índices para stock
CREATE INDEX IF NOT EXISTS idx_stock_tipo_movimiento ON stock(tipo_movimiento);
CREATE INDEX IF NOT EXISTS idx_stock_referencia ON stock(referencia_tipo, referencia_id);
CREATE INDEX IF NOT EXISTS idx_stock_producto_fecha ON stock(id_producto, created_at);
CREATE INDEX IF NOT EXISTS idx_stock_ubicacion_destino ON stock(ubicacion_destino);
CREATE INDEX IF NOT EXISTS idx_stock_created_at ON stock(created_at);

-- =====================================================
-- 5. VERIFICAR CAMBIOS
-- =====================================================

-- Verificar estructura de productos
-- DESCRIBE productos;

-- Verificar estructura de stock
-- DESCRIBE stock;

-- Verificar categorías
-- SELECT * FROM categorias_productos;

-- =====================================================
-- FIN DEL SCRIPT
-- =====================================================
