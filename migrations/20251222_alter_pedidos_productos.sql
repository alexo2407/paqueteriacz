-- Migración: Mejorar tabla pedidos_productos
-- Fecha: 2025-12-22
-- Descripción: Agregar campos de precio y descuentos para mantener histórico

-- Agregar nuevas columnas
ALTER TABLE pedidos_productos
    ADD COLUMN precio_unitario_usd DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT 'Precio unitario en USD al momento de la compra' AFTER cantidad,
    ADD COLUMN descuento_porcentaje DECIMAL(5,2) DEFAULT 0 COMMENT 'Descuento aplicado en porcentaje' AFTER precio_unitario_usd,
    ADD COLUMN subtotal_usd DECIMAL(10,2) GENERATED ALWAYS AS (
        (cantidad - COALESCE(cantidad_devuelta, 0)) * precio_unitario_usd * (1 - COALESCE(descuento_porcentaje, 0) / 100)
    ) STORED COMMENT 'Subtotal calculado automáticamente' AFTER descuento_porcentaje,
    ADD COLUMN notas TEXT COMMENT 'Notas específicas del producto en el pedido' AFTER subtotal_usd;

-- Actualizar precios de productos existentes basándose en el precio actual del producto
UPDATE pedidos_productos pp
INNER JOIN productos p ON pp.id_producto = p.id
SET pp.precio_unitario_usd = COALESCE(p.precio_usd, 0)
WHERE pp.precio_unitario_usd = 0 OR pp.precio_unitario_usd IS NULL;

-- Crear índices
CREATE INDEX idx_pedidos_productos_pedido ON pedidos_productos(id_pedido);
CREATE INDEX idx_pedidos_productos_producto ON pedidos_productos(id_producto);

-- Agregar constraint para validar que el descuento no sea mayor a 100%
ALTER TABLE pedidos_productos
    ADD CONSTRAINT chk_descuento_valido 
    CHECK (descuento_porcentaje >= 0 AND descuento_porcentaje <= 100);

-- Comentario en la tabla
ALTER TABLE pedidos_productos 
COMMENT='Productos incluidos en cada pedido con precios históricos';
