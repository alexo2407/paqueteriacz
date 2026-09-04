-- =====================================================
-- Migración 019: Permitir slug no único en forwarding_providers
-- Permite tener múltiples proveedores configurados con el mismo driver/slug
-- (por ejemplo: LogisPro México, LogisPro Ecuador, LogisPro Guatemala, etc.)
-- =====================================================

-- Eliminar restricción UNIQUE en la columna slug si existe
ALTER TABLE forwarding_providers DROP INDEX slug;

-- Agregar índice normal (no único) para optimizar búsquedas por slug
ALTER TABLE forwarding_providers ADD INDEX idx_slug (slug);
