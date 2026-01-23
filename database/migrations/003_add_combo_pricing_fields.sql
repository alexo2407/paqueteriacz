-- =====================================================
-- Migration: Add combo pricing fields to pedidos table
-- Date: 2026-01-22
-- Description: Adds precio_total_local, precio_total_usd, 
--              and tasa_conversion_usd for combo pricing support
-- =====================================================

-- Add precio_total_local
SET @exist := (SELECT COUNT(*) 
               FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'pedidos' 
               AND column_name = 'precio_total_local');

SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Column precio_total_local already exists" AS message',
    'ALTER TABLE pedidos ADD COLUMN precio_total_local DECIMAL(10,2) NULL AFTER precio_usd');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add precio_total_usd
SET @exist := (SELECT COUNT(*) 
               FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'pedidos' 
               AND column_name = 'precio_total_usd');

SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Column precio_total_usd already exists" AS message',
    'ALTER TABLE pedidos ADD COLUMN precio_total_usd DECIMAL(10,2) NULL AFTER precio_total_local');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add tasa_conversion_usd
SET @exist := (SELECT COUNT(*) 
               FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'pedidos' 
               AND column_name = 'tasa_conversion_usd');

SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Column tasa_conversion_usd already exists" AS message',
    'ALTER TABLE pedidos ADD COLUMN tasa_conversion_usd DECIMAL(10,4) NULL AFTER precio_total_usd');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add es_combo flag
SET @exist := (SELECT COUNT(*) 
               FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'pedidos' 
               AND column_name = 'es_combo');

SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Column es_combo already exists" AS message',
    'ALTER TABLE pedidos ADD COLUMN es_combo TINYINT(1) DEFAULT 0 AFTER tasa_conversion_usd');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify changes
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'pedidos' 
AND COLUMN_NAME IN ('precio_total_local', 'precio_total_usd', 'tasa_conversion_usd', 'es_combo')
ORDER BY ORDINAL_POSITION;
