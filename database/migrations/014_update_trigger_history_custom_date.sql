-- =============================================================================
-- Migración: 014_update_trigger_history_custom_date.sql
-- Fecha:      2026-04-22
-- Descripción: Actualiza el trigger after_pedido_update_estado para permitir
--              pasar una fecha de creación personalizada vía @current_created_at.
-- =============================================================================

DROP TRIGGER IF EXISTS after_pedido_update_estado;

DELIMITER $$

CREATE TRIGGER after_pedido_update_estado
AFTER UPDATE ON pedidos
FOR EACH ROW
BEGIN
    IF OLD.id_estado <> NEW.id_estado OR (OLD.id_estado IS NULL AND NEW.id_estado IS NOT NULL) THEN
        INSERT INTO pedidos_historial_estados (
            id_pedido,
            id_estado_anterior,
            id_estado_nuevo,
            id_usuario,
            observaciones,
            created_at
        ) VALUES (
            NEW.id,
            OLD.id_estado,
            NEW.id_estado,
            COALESCE(@current_user_id, 1),
            COALESCE(@current_observaciones, 'Estado cambiado automáticamente'),
            COALESCE(@current_created_at, NOW())
        );
    END IF;
END$$

DELIMITER ;
