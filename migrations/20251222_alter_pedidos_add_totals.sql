-- Migración: Ampliar tabla pedidos con totales y prioridad
-- Fecha: 2025-12-22
-- Descripción: Agregar campos calculados de totales, fechas y prioridad

-- Agregar nuevas columnas
ALTER TABLE pedidos
    ADD COLUMN subtotal_usd DECIMAL(10,2) COMMENT 'Suma de subtotales de productos' AFTER precio_usd,
    ADD COLUMN descuento_usd DECIMAL(10,2) DEFAULT 0 COMMENT 'Descuento total aplicado al pedido' AFTER subtotal_usd,
    ADD COLUMN impuestos_usd DECIMAL(10,2) DEFAULT 0 COMMENT 'Impuestos aplicados' AFTER descuento_usd,
    ADD COLUMN total_usd DECIMAL(10,2) COMMENT 'Total final del pedido' AFTER impuestos_usd,
    ADD COLUMN fecha_estimada_entrega DATE COMMENT 'Fecha estimada de entrega' AFTER fecha_ingreso,
    ADD COLUMN prioridad ENUM('baja', 'normal', 'alta', 'urgente') DEFAULT 'normal' COMMENT 'Prioridad del pedido' AFTER fecha_estimada_entrega;

-- Crear función para calcular el subtotal de un pedido
DELIMITER $$

CREATE FUNCTION calcular_subtotal_pedido(pedido_id INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE subtotal DECIMAL(10,2);
    
    SELECT COALESCE(SUM(subtotal_usd), 0)
    INTO subtotal
    FROM pedidos_productos
    WHERE id_pedido = pedido_id;
    
    RETURN subtotal;
END$$

DELIMITER ;

-- Actualizar subtotales de pedidos existentes
UPDATE pedidos p
SET p.subtotal_usd = calcular_subtotal_pedido(p.id);

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
CREATE INDEX idx_pedidos_prioridad ON pedidos(prioridad);
CREATE INDEX idx_pedidos_fecha_estimada ON pedidos(fecha_estimada_entrega);
CREATE INDEX idx_pedidos_total ON pedidos(total_usd);

-- Crear trigger para actualizar totales cuando cambian los productos
DELIMITER $$

CREATE TRIGGER after_pedidos_productos_change
AFTER INSERT ON pedidos_productos
FOR EACH ROW
BEGIN
    UPDATE pedidos
    SET subtotal_usd = calcular_subtotal_pedido(NEW.id_pedido),
        total_usd = calcular_subtotal_pedido(NEW.id_pedido) - COALESCE(descuento_usd, 0) + COALESCE(impuestos_usd, 0)
    WHERE id = NEW.id_pedido;
END$$

CREATE TRIGGER after_pedidos_productos_update
AFTER UPDATE ON pedidos_productos
FOR EACH ROW
BEGIN
    UPDATE pedidos
    SET subtotal_usd = calcular_subtotal_pedido(NEW.id_pedido),
        total_usd = calcular_subtotal_pedido(NEW.id_pedido) - COALESCE(descuento_usd, 0) + COALESCE(impuestos_usd, 0)
    WHERE id = NEW.id_pedido;
END$$

CREATE TRIGGER after_pedidos_productos_delete
AFTER DELETE ON pedidos_productos
FOR EACH ROW
BEGIN
    UPDATE pedidos
    SET subtotal_usd = calcular_subtotal_pedido(OLD.id_pedido),
        total_usd = calcular_subtotal_pedido(OLD.id_pedido) - COALESCE(descuento_usd, 0) + COALESCE(impuestos_usd, 0)
    WHERE id = OLD.id_pedido;
END$$

DELIMITER ;

-- Comentario en la tabla
ALTER TABLE pedidos 
COMMENT='Pedidos con cálculos automáticos de totales';
