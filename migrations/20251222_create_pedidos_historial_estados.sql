-- Migración: Crear tabla de historial de estados de pedidos
-- Fecha: 2025-12-22
-- Descripción: Tabla para registrar todos los cambios de estado de un pedido

CREATE TABLE IF NOT EXISTS pedidos_historial_estados (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_estado_anterior INT NULL COMMENT 'Estado previo (NULL si es el primer estado)',
    id_estado_nuevo INT NOT NULL COMMENT 'Nuevo estado asignado',
    id_usuario INT NOT NULL COMMENT 'Usuario que realizó el cambio',
    observaciones TEXT COMMENT 'Notas o comentarios sobre el cambio',
    ip_address VARCHAR(45) COMMENT 'IP desde donde se realizó el cambio',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_estado_anterior) REFERENCES estados_pedidos(id) ON DELETE SET NULL,
    FOREIGN KEY (id_estado_nuevo) REFERENCES estados_pedidos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE RESTRICT,
    
    -- Índices
    INDEX idx_historial_pedido (id_pedido),
    INDEX idx_historial_estado_nuevo (id_estado_nuevo),
    INDEX idx_historial_usuario (id_usuario),
    INDEX idx_historial_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historial de cambios de estado de pedidos';

-- Poblar historial con el estado actual de los pedidos existentes
-- Esto crea el registro inicial para cada pedido
INSERT INTO pedidos_historial_estados (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario, observaciones, created_at)
SELECT 
    p.id,
    NULL as id_estado_anterior,
    p.id_estado,
    COALESCE(p.id_vendedor, p.id_proveedor, 1) as id_usuario,
    'Estado inicial migrado desde tabla pedidos',
    p.fecha_ingreso
FROM pedidos p
WHERE p.id_estado IS NOT NULL
ON DUPLICATE KEY UPDATE id_pedido = id_pedido;

-- Crear trigger para registrar automáticamente cambios de estado
DELIMITER $$

CREATE TRIGGER after_pedido_update_estado
AFTER UPDATE ON pedidos
FOR EACH ROW
BEGIN
    -- Solo registrar si el estado cambió
    IF OLD.id_estado <> NEW.id_estado OR (OLD.id_estado IS NULL AND NEW.id_estado IS NOT NULL) THEN
        INSERT INTO pedidos_historial_estados (
            id_pedido, 
            id_estado_anterior, 
            id_estado_nuevo, 
            id_usuario,
            observaciones
        ) VALUES (
            NEW.id,
            OLD.id_estado,
            NEW.id_estado,
            COALESCE(@current_user_id, 1),
            CONCAT('Estado cambiado automáticamente')
        );
    END IF;
END$$

DELIMITER ;
