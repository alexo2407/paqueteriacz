-- =============================================================================
-- 021_complete_logistica_bodega_constraints.sql
--
-- Migración: Fase 3A — Restricciones e Índices Complementarios de Logística Bodega
--
-- Agrega las restricciones e índices complementarios que requerían la existencia
-- previa de la tabla `logistica_escaneos` (creada en la migración 019):
--   1. FK fk_recepciones_escaneo en logistica_recepciones(id_escaneo) -> logistica_escaneos(id)
--   2. Índice idx_historial_tipo en logistica_ubicacion_historial(tipo_movimiento)
--
-- SEGURIDAD E IDEMPOTENCIA:
--   - Idempotente: consulta information_schema mediante SQL dinámico antes de cada ALTER.
--   - Compatible con MariaDB 10.4+.
--   - Sin DROP, DELETE, UPDATE DML, INSERT ni TRUNCATE.
--   - No modifica usuarios, pedidos, stock, inventario ni reservas.
-- =============================================================================

-- ── 1. Agregar FK fk_recepciones_escaneo si no existe ────────────────────────
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'logistica_recepciones'
      AND CONSTRAINT_NAME = 'fk_recepciones_escaneo'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql_fk = IF(@constraint_exists = 0,
    'ALTER TABLE logistica_recepciones ADD CONSTRAINT fk_recepciones_escaneo FOREIGN KEY (id_escaneo) REFERENCES logistica_escaneos (id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "FK fk_recepciones_escaneo ya existe" AS status'
);

PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;


-- ── 2. Agregar índice idx_historial_tipo si no existe ────────────────────────
SET @index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'logistica_ubicacion_historial'
      AND INDEX_NAME   = 'idx_historial_tipo'
);

SET @sql_idx = IF(@index_exists = 0,
    'ALTER TABLE logistica_ubicacion_historial ADD INDEX idx_historial_tipo (tipo_movimiento)',
    'SELECT "Índice idx_historial_tipo ya existe" AS status'
);

PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
