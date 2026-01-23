-- =====================================================
-- Migration: Remove unused columns from pedidos table
-- Date: 2026-01-22
-- Description: Cleanup of 4 verified unused attributes
-- =====================================================

-- Drop unused fecha_estimada_entrega (only used in legacy CSV export, not in forms)
SET @exist := (SELECT COUNT(*) 
               FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'pedidos' 
               AND column_name = 'fecha_estimada_entrega');

SET @sqlstmt := IF(@exist > 0, 
    'ALTER TABLE pedidos DROP COLUMN fecha_estimada_entrega',
    'SELECT "Column fecha_estimada_entrega already dropped" AS message');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop unused prioridad (not used in any form or logic)
SET @exist := (SELECT COUNT(*) 
               FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'pedidos' 
               AND column_name = 'prioridad');

SET @sqlstmt := IF(@exist > 0, 
    'ALTER TABLE pedidos DROP COLUMN prioridad',
    'SELECT "Column prioridad already dropped" AS message');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop unused descuento_usd (not implemented in application)
SET @exist := (SELECT COUNT(*) 
               FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'pedidos' 
               AND column_name = 'descuento_usd');

SET @sqlstmt := IF(@exist > 0, 
    'ALTER TABLE pedidos DROP COLUMN descuento_usd',
    'SELECT "Column descuento_usd already dropped" AS message');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop unused impuestos_usd (not implemented in application)
SET @exist := (SELECT COUNT(*) 
               FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'pedidos' 
               AND column_name = 'impuestos_usd');

SET @sqlstmt := IF(@exist > 0, 
    'ALTER TABLE pedidos DROP COLUMN impuestos_usd',
    'SELECT "Column impuestos_usd already dropped" AS message');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Note: Keeping these columns as they ARE being used:
-- - observaciones_combo (used in ComboController.php)
-- - subtotal_usd, total_usd (used in calculations in pedido.php)
-- - precio_total_usd, precio_total_local (used in forms)

-- Verify final structure
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'pedidos'
ORDER BY ORDINAL_POSITION;
