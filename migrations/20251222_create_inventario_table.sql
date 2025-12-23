-- Migración: Crear tabla de inventario consolidado
-- Fecha: 2025-12-22
-- Descripción: Tabla para mantener el inventario actual consolidado por producto y ubicación

CREATE TABLE IF NOT EXISTS inventario (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_producto INT NOT NULL,
    ubicacion VARCHAR(100) DEFAULT 'Principal' COMMENT 'Ubicación física del inventario',
    cantidad_disponible INT DEFAULT 0 COMMENT 'Cantidad disponible para venta',
    cantidad_reservada INT DEFAULT 0 COMMENT 'Cantidad reservada en pedidos pendientes',
    costo_promedio DECIMAL(10,2) COMMENT 'Costo promedio ponderado del inventario',
    ultima_entrada TIMESTAMP NULL COMMENT 'Fecha de última entrada de stock',
    ultima_salida TIMESTAMP NULL COMMENT 'Fecha de última salida de stock',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key
    FOREIGN KEY (id_producto) REFERENCES productos(id) ON DELETE CASCADE,
    
    -- Constraint único: un producto solo puede tener un registro por ubicación
    UNIQUE KEY uk_producto_ubicacion (id_producto, ubicacion),
    
    -- Índices para optimización
    INDEX idx_inventario_producto (id_producto),
    INDEX idx_inventario_ubicacion (ubicacion),
    INDEX idx_inventario_disponible (cantidad_disponible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Inventario consolidado por producto y ubicación';

-- Poblar tabla de inventario con datos actuales de stock
-- Esto suma todos los movimientos de stock por producto
INSERT INTO inventario (id_producto, ubicacion, cantidad_disponible, cantidad_reservada, created_at)
SELECT 
    s.id_producto,
    'Principal' as ubicacion,
    COALESCE(SUM(s.cantidad), 0) as cantidad_disponible,
    0 as cantidad_reservada,
    NOW() as created_at
FROM stock s
WHERE s.id_producto IS NOT NULL
GROUP BY s.id_producto
ON DUPLICATE KEY UPDATE 
    cantidad_disponible = VALUES(cantidad_disponible),
    updated_at = NOW();

-- Crear trigger para actualizar inventario cuando hay movimiento de stock
DELIMITER $$

CREATE TRIGGER after_stock_insert
AFTER INSERT ON stock
FOR EACH ROW
BEGIN
    -- Actualizar o crear registro en inventario
    -- Usamos 'Principal' como ubicación por defecto ya que ubicacion_destino se agrega después
    INSERT INTO inventario (id_producto, ubicacion, cantidad_disponible, ultima_entrada, ultima_salida, updated_at)
    VALUES (
        NEW.id_producto,
        'Principal',
        NEW.cantidad,
        IF(NEW.cantidad > 0, NOW(), NULL),
        IF(NEW.cantidad < 0, NOW(), NULL),
        NOW()
    )
    ON DUPLICATE KEY UPDATE
        cantidad_disponible = cantidad_disponible + NEW.cantidad,
        ultima_entrada = IF(NEW.cantidad > 0, NOW(), ultima_entrada),
        ultima_salida = IF(NEW.cantidad < 0, NOW(), ultima_salida),
        updated_at = NOW();
END$$

DELIMITER ;

