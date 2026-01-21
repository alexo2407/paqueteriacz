-- =====================================================
-- Migration: Add Combo-Level Pricing to Pedidos
-- Date: 2026-01-21
-- Description: Add fields to store total combo price without
--              distributing costs to individual products.
--              Supports multi-currency pricing.
-- =====================================================

-- =====================================================
-- PEDIDOS TABLE - Add Combo Pricing Fields
-- =====================================================

-- Add precio_total_local (precio total en moneda local del proveedor)
SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'pedidos' 
               AND column_name = 'precio_total_local');
SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Column precio_total_local already exists" AS message',
    'ALTER TABLE pedidos ADD COLUMN precio_total_local DECIMAL(10,2) NULL COMMENT "Precio total del combo en moneda local del proveedor"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add precio_total_usd (precio total convertido a USD)
SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'pedidos' 
               AND column_name = 'precio_total_usd');
SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Column precio_total_usd already exists" AS message',
    'ALTER TABLE pedidos ADD COLUMN precio_total_usd DECIMAL(10,2) NULL COMMENT "Precio total del combo convertido a USD"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add tasa_conversion_usd (tasa de conversión al momento del pedido)
SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'pedidos' 
               AND column_name = 'tasa_conversion_usd');
SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Column tasa_conversion_usd already exists" AS message',
    'ALTER TABLE pedidos ADD COLUMN tasa_conversion_usd DECIMAL(10,6) NULL COMMENT "Tasa de conversión usada al crear el pedido"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- PAISES TABLE - Add Currency Relationship
-- =====================================================

-- Add id_moneda_local (moneda local del país)
SET @exist := (SELECT COUNT(*) FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'paises' 
               AND column_name = 'id_moneda_local');
SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Column id_moneda_local already exists" AS message',
    'ALTER TABLE paises ADD COLUMN id_moneda_local INT NULL COMMENT "Moneda local del país"');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint for id_moneda_local (if not exists)
SET @exist := (SELECT COUNT(*) FROM information_schema.table_constraints 
               WHERE table_schema = DATABASE() 
               AND table_name = 'paises' 
               AND constraint_name = 'fk_paises_moneda_local');
SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Foreign key fk_paises_moneda_local already exists" AS message',
    'ALTER TABLE paises ADD CONSTRAINT fk_paises_moneda_local FOREIGN KEY (id_moneda_local) REFERENCES monedas(id)');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- POPULATE LOCAL CURRENCIES FOR COUNTRIES
-- =====================================================

-- Update Costa Rica with Colones (CRC)
UPDATE paises p
SET p.id_moneda_local = (SELECT id FROM monedas WHERE codigo = 'CRC' LIMIT 1)
WHERE (p.nombre LIKE '%Costa Rica%' OR p.codigo_iso IN ('CR', 'CRC'))
  AND p.id_moneda_local IS NULL
  AND EXISTS (SELECT 1 FROM monedas WHERE codigo = 'CRC');

-- Update Nicaragua with Córdobas (NIO)
UPDATE paises p
SET p.id_moneda_local = (SELECT id FROM monedas WHERE codigo = 'NIO' LIMIT 1)
WHERE (p.nombre LIKE '%Nicaragua%' OR p.codigo_iso IN ('NI', 'NIC'))
  AND p.id_moneda_local IS NULL
  AND EXISTS (SELECT 1 FROM monedas WHERE codigo = 'NIO');

-- Update Guatemala with Quetzales (GTQ)
UPDATE paises p
SET p.id_moneda_local = (SELECT id FROM monedas WHERE codigo = 'GTQ' LIMIT 1)
WHERE (p.nombre LIKE '%Guatemala%' OR p.codigo_iso IN ('GT', 'GTM'))
  AND p.id_moneda_local IS NULL
  AND EXISTS (SELECT 1 FROM monedas WHERE codigo = 'GTQ');

-- Update Honduras with Lempiras (HNL)
UPDATE paises p
SET p.id_moneda_local = (SELECT id FROM monedas WHERE codigo = 'HNL' LIMIT 1)
WHERE (p.nombre LIKE '%Honduras%' OR p.codigo_iso IN ('HN', 'HND'))
  AND p.id_moneda_local IS NULL
  AND EXISTS (SELECT 1 FROM monedas WHERE codigo = 'HNL');

-- Update El Salvador with USD (already uses USD)
UPDATE paises p
SET p.id_moneda_local = (SELECT id FROM monedas WHERE codigo = 'USD' LIMIT 1)
WHERE (p.nombre LIKE '%El Salvador%' OR p.codigo_iso IN ('SV', 'SLV'))
  AND p.id_moneda_local IS NULL
  AND EXISTS (SELECT 1 FROM monedas WHERE codigo = 'USD');

-- =====================================================
-- VERIFICATION
-- =====================================================

-- Show new columns in pedidos table
SELECT 
    'pedidos' AS table_name,
    column_name,
    column_type,
    is_nullable,
    column_comment
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'pedidos'
  AND column_name IN ('precio_total_local', 'precio_total_usd', 'tasa_conversion_usd')
ORDER BY ordinal_position;

-- Show currency assignments for countries
SELECT 
    p.id,
    p.nombre AS pais,
    p.codigo AS codigo_pais,
    m.codigo AS moneda_codigo,
    m.nombre AS moneda_nombre,
    m.tasa_usd
FROM paises p
LEFT JOIN monedas m ON p.id_moneda_local = m.id
WHERE p.id_moneda_local IS NOT NULL
ORDER BY p.nombre;

-- =====================================================
-- ROLLBACK SCRIPT (if needed)
-- =====================================================
-- Uncomment and run these lines to remove all changes:
/*
ALTER TABLE paises
    DROP FOREIGN KEY IF EXISTS fk_paises_moneda_local,
    DROP COLUMN IF EXISTS id_moneda_local;

ALTER TABLE pedidos 
    DROP COLUMN IF EXISTS precio_total_local,
    DROP COLUMN IF EXISTS precio_total_usd,
    DROP COLUMN IF EXISTS tasa_conversion_usd;
*/
