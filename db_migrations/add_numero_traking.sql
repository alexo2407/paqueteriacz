-- ─────────────────────────────────────────────────────────────────────────────
-- Migración: Agregar campo numero_traking a tabla pedidos
-- Cliente: econglobaluruguay@rutaexlatam.com
-- Fecha: 2026-07-27
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `pedidos`
    ADD COLUMN `numero_traking` VARCHAR(255) NULL DEFAULT NULL
    COMMENT 'Número de tracking/guía del operador logístico (opcional). Visible para cliente y proveedor.'
    AFTER `courier_service`;

-- Índice para búsqueda por número de tracking
ALTER TABLE `pedidos`
    ADD INDEX `idx_pedidos_numero_traking` (`numero_traking`(64));
