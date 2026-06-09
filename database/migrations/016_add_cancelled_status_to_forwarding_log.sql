-- =====================================================
-- Migración 016: Agregar estado 'cancelled' a logs
-- =====================================================

ALTER TABLE forwarding_log MODIFY COLUMN status ENUM('success', 'failed', 'pending', 'cancelled') NOT NULL DEFAULT 'pending';
