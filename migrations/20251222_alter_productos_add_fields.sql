-- Migración: Ampliar tabla productos
-- Fecha: 2025-12-22
-- Descripción: Agregar campos adicionales para mejorar gestión de productos

-- Agregar nuevas columnas a la tabla productos
ALTER TABLE productos 
    ADD COLUMN sku VARCHAR(100) UNIQUE AFTER id,
    ADD COLUMN categoria_id INT AFTER sku,
    ADD COLUMN marca VARCHAR(100) AFTER categoria_id,
    ADD COLUMN unidad_medida ENUM('unidad', 'kg', 'litro', 'metro', 'caja', 'paquete') DEFAULT 'unidad' AFTER marca,
    ADD COLUMN stock_minimo INT DEFAULT 10 COMMENT 'Stock mínimo para alerta' AFTER unidad_medida,
    ADD COLUMN stock_maximo INT DEFAULT 100 COMMENT 'Stock máximo recomendado' AFTER stock_minimo,
    ADD COLUMN activo BOOLEAN DEFAULT TRUE AFTER stock_maximo,
    ADD COLUMN imagen_url VARCHAR(500) AFTER activo,
    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER imagen_url,
    ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Agregar foreign key a categorias
ALTER TABLE productos
    ADD CONSTRAINT fk_producto_categoria 
    FOREIGN KEY (categoria_id) REFERENCES categorias_productos(id) 
    ON DELETE SET NULL;

-- Crear índices para optimizar consultas
CREATE INDEX idx_producto_categoria ON productos(categoria_id);
CREATE INDEX idx_producto_activo ON productos(activo);
CREATE INDEX idx_producto_sku ON productos(sku);
CREATE INDEX idx_producto_marca ON productos(marca);

-- Actualizar productos existentes con valores por defecto
UPDATE productos 
SET categoria_id = (SELECT id FROM categorias_productos WHERE nombre = 'Otros' LIMIT 1)
WHERE categoria_id IS NULL;

UPDATE productos 
SET activo = TRUE 
WHERE activo IS NULL;
