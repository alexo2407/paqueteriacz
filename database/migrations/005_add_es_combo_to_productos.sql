-- Add es_combo column to productos table
-- This allows products to be marked as "pre-packaged combos" so the API
-- can automatically flag orders containing them as es_combo = 1.

ALTER TABLE productos
ADD COLUMN es_combo TINYINT(1) DEFAULT 0 COMMENT 'Flag que indica si el producto es un combo pre-empaquetado' AFTER activo;

-- Index for performance
CREATE INDEX idx_productos_es_combo ON productos(es_combo);
