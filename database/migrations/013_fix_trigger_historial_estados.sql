-- =============================================================================
-- Migración: 013_fix_trigger_historial_estados.sql
-- Fecha:      2026-03-04
-- Descripción: Actualiza el trigger after_pedido_update_estado para capturar
--              el usuario real y la observación real del cambio de estado.
--
--              ANTES: id_usuario siempre era 1 (Admin General por fallback)
--                     observaciones siempre era 'Estado cambiado automáticamente'
--              AHORA: usa @current_user_id y @current_observaciones que el
--                     código PHP setea antes de cada UPDATE a pedidos.
--
-- Impacto:    Solo afecta registros FUTUROS en pedidos_historial_estados.
--             Los registros históricos existentes NO se modifican.
--
-- Rollback:   Ver sección ROLLBACK al final del archivo.
-- =============================================================================

-- Verificar estado actual del trigger (informativo)
-- SELECT TRIGGER_NAME, EVENT_MANIPULATION, ACTION_STATEMENT
-- FROM information_schema.TRIGGERS
-- WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'after_pedido_update_estado';

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
            observaciones
        ) VALUES (
            NEW.id,
            OLD.id_estado,
            NEW.id_estado,
            COALESCE(@current_user_id, 1),
            COALESCE(@current_observaciones, 'Estado cambiado automáticamente')
        );
    END IF;
END$$

DELIMITER ;

-- Verificar que el trigger quedó correctamente
SHOW TRIGGERS WHERE `Table` = 'pedidos';

-- =============================================================================
-- ROLLBACK (ejecutar solo si necesitas revertir)
-- =============================================================================
-- DROP TRIGGER IF EXISTS after_pedido_update_estado;
--
-- DELIMITER $$
-- CREATE TRIGGER after_pedido_update_estado
-- AFTER UPDATE ON pedidos
-- FOR EACH ROW
-- BEGIN
--     IF OLD.id_estado <> NEW.id_estado OR (OLD.id_estado IS NULL AND NEW.id_estado IS NOT NULL) THEN
--         INSERT INTO pedidos_historial_estados (
--             id_pedido,
--             id_estado_anterior,
--             id_estado_nuevo,
--             id_usuario,
--             observaciones
--         ) VALUES (
--             NEW.id,
--             OLD.id_estado,
--             NEW.id_estado,
--             COALESCE(@current_user_id, 1),
--             CONCAT('Estado cambiado automáticamente')
--         );
--     END IF;
-- END$$
-- DELIMITER ;
-- =============================================================================
