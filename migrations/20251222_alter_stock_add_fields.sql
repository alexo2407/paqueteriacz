-- Migración: Mejorar tabla stock
-- Fecha: 2025-12-22
-- Descripción: Agregar campos para mejor trazabilidad de movimientos de stock

-- Agregar nuevas columnas
ALTER TABLE stock
    ADD COLUMN tipo_movimiento ENUM('entrada', 'salida', 'ajuste', 'devolucion', 'transferencia') NOT NULL DEFAULT 'entrada' AFTER cantidad,
    ADD COLUMN referencia_tipo ENUM('pedido', 'compra', 'ajuste_manual', 'devolucion', 'transferencia') NULL AFTER tipo_movimiento,
    ADD COLUMN referencia_id INT NULL COMMENT 'ID del documento que generó el movimiento' AFTER referencia_tipo,
    ADD COLUMN motivo VARCHAR(255) COMMENT 'Descripción del motivo del movimiento' AFTER referencia_id,
    ADD COLUMN ubicacion_origen VARCHAR(100) AFTER motivo,
    ADD COLUMN ubicacion_destino VARCHAR(100) DEFAULT 'Principal' AFTER ubicacion_origen,
    ADD COLUMN costo_unitario DECIMAL(10,2) COMMENT 'Costo unitario al momento del movimiento' AFTER ubicacion_destino,
    ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER costo_unitario;

-- Actualizar registros existentes con valores por defecto
UPDATE stock 
SET tipo_movimiento = CASE 
    WHEN cantidad > 0 THEN 'entrada'
    WHEN cantidad < 0 THEN 'salida'
    ELSE 'ajuste'
END,
ubicacion_destino = 'Principal',
created_at = COALESCE(updated_at, NOW())
WHERE tipo_movimiento IS NULL;

-- Actualizar referencia_tipo para movimientos existentes relacionados con pedidos
-- Esto es una aproximación basada en la cantidad negativa
UPDATE stock 
SET referencia_tipo = 'pedido'
WHERE cantidad < 0 AND referencia_tipo IS NULL;

-- Crear índices para mejorar rendimiento
CREATE INDEX idx_stock_tipo_movimiento ON stock(tipo_movimiento);
CREATE INDEX idx_stock_referencia ON stock(referencia_tipo, referencia_id);
CREATE INDEX idx_stock_producto_fecha ON stock(id_producto, created_at);
CREATE INDEX idx_stock_ubicacion_destino ON stock(ubicacion_destino);
CREATE INDEX idx_stock_created_at ON stock(created_at);

-- Comentarios en la tabla
ALTER TABLE stock COMMENT='Movimientos de inventario con trazabilidad completa';
