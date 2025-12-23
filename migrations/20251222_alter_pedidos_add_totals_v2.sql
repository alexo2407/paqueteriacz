-- Migración: Ampliar tabla pedidos con totales y prioridad (Versión corregida sin funciones)
-- Fecha: 2025-12-22
-- Descripción: Agregar campos calculados de totales, fechas y prioridad

-- Agregar nuevas columnas
ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS subtotal_usd DECIMAL(10,2) COMMENT 'Suma de subtotales de productos' AFTER precio_usd,
    ADD COLUMN IF NOT EXISTS descuento_usd DECIMAL(10,2) DEFAULT 0 COMMENT 'Descuento total aplicado al pedido' AFTER subtotal_usd,
    ADD COLUMN IF NOT EXISTS impuestos_usd DECIMAL(10,2) DEFAULT 0 COMMENT 'Impuestos aplicados' AFTER descuento_usd,
    ADD COLUMN IF NOT EXISTS total_usd DECIMAL(10,2) COMMENT 'Total final del pedido' AFTER impuestos_usd,
    ADD COLUMN IF NOT EXISTS fecha_estimada_entrega DATE COMMENT 'Fecha estimada de entrega' AFTER fecha_ingreso,
    ADD COLUMN IF NOT EXISTS prioridad ENUM('baja', 'normal', 'alta', 'urgente') DEFAULT 'normal' COMMENT 'Prioridad del pedido' AFTER fecha_estimada_entrega;

-- Actualizar subtotales de pedidos existentes desde pedidos_productos
UPDATE pedidos p
SET p.subtotal_usd = (
    SELECT COALESCE(SUM(pp.subtotal_usd), 0)
    FROM pedidos_productos pp
    WHERE pp.id_pedido = p.id
);

-- Actualizar total_usd basado en subtotal, descuento e impuestos
UPDATE pedidos
SET total_usd = COALESCE(subtotal_usd, 0) - COALESCE(descuento_usd, 0) + COALESCE(impuestos_usd, 0)
WHERE total_usd IS NULL;

-- Asegurar que precio_usd esté sincronizado con total_usd
UPDATE pedidos
SET precio_usd = total_usd
WHERE precio_usd IS NULL OR precio_usd = 0;

-- Establecer prioridad normal para pedidos existentes
UPDATE pedidos
SET prioridad = 'normal'
WHERE prioridad IS NULL;

-- Crear índices
CREATE INDEX IF NOT EXISTS idx_pedidos_prioridad ON pedidos(prioridad);
CREATE INDEX IF NOT EXISTS idx_pedidos_fecha_estimada ON pedidos(fecha_estimada_entrega);
CREATE INDEX IF NOT EXISTS idx_pedidos_total ON pedidos(total_usd);

-- Comentario en la tabla
ALTER TABLE pedidos 
COMMENT='Pedidos con campos de totales y prioridad';
