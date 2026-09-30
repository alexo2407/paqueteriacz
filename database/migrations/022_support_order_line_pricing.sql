-- =====================================================
-- Migration: Ensure Order Line Pricing Support in pedidos_productos
-- Date: 2026-09-30
-- Description: Ensures precio_unitario_usd and subtotal_usd exist in pedidos_productos
-- =====================================================

-- Verify column precio_unitario_usd exists, add if missing
SET @exist_pu := (SELECT COUNT(*) 
                  FROM information_schema.columns 
                  WHERE table_schema = DATABASE() 
                  AND table_name = 'pedidos_productos' 
                  AND column_name = 'precio_unitario_usd');

SET @sqlstmt_pu := IF(@exist_pu = 0, 
    'ALTER TABLE pedidos_productos ADD COLUMN precio_unitario_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT \'Precio unitario en USD al momento de la compra\' AFTER cantidad',
    'SELECT "Column precio_unitario_usd already exists" AS message');

PREPARE stmt_pu FROM @sqlstmt_pu;
EXECUTE stmt_pu;
DEALLOCATE PREPARE stmt_pu;

-- Verify column descuento_porcentaje exists, add if missing
SET @exist_dp := (SELECT COUNT(*) 
                  FROM information_schema.columns 
                  WHERE table_schema = DATABASE() 
                  AND table_name = 'pedidos_productos' 
                  AND column_name = 'descuento_porcentaje');

SET @sqlstmt_dp := IF(@exist_dp = 0, 
    'ALTER TABLE pedidos_productos ADD COLUMN descuento_porcentaje DECIMAL(5,2) DEFAULT 0.00 COMMENT \'Descuento aplicado en porcentaje\' AFTER precio_unitario_usd',
    'SELECT "Column descuento_porcentaje already exists" AS message');

PREPARE stmt_dp FROM @sqlstmt_dp;
EXECUTE stmt_dp;
DEALLOCATE PREPARE stmt_dp;
