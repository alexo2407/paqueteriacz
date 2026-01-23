-- =====================================================
-- Migration: Add id_moneda_local to paises table
-- Date: 2026-01-22
-- Description: Adds foreign key to link countries with 
--              their local currency for auto-selection
-- =====================================================

-- Add column if it doesn't exist
SET @exist := (SELECT COUNT(*) 
               FROM information_schema.columns 
               WHERE table_schema = DATABASE() 
               AND table_name = 'paises' 
               AND column_name = 'id_moneda_local');

SET @sqlstmt := IF(@exist > 0, 
    'SELECT "Column id_moneda_local already exists" AS message',
    'ALTER TABLE paises ADD COLUMN id_moneda_local INT(11) NULL AFTER codigo_iso');

PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update countries with their local currencies
UPDATE paises SET id_moneda_local = 8 WHERE codigo_iso = 'GT'; -- Guatemala = GTQ
UPDATE paises SET id_moneda_local = 5 WHERE codigo_iso = 'NI'; -- Nicaragua = NIO
UPDATE paises SET id_moneda_local = 6 WHERE codigo_iso = 'CR'; -- Costa Rica = CRC
UPDATE paises SET id_moneda_local = 3 WHERE codigo_iso = 'CO'; -- Colombia = COP
UPDATE paises SET id_moneda_local = 1 WHERE codigo_iso = 'US'; -- USA = USD

-- Add foreign key constraint
SET @exist_fk := (SELECT COUNT(*) 
                  FROM information_schema.table_constraints 
                  WHERE table_schema = DATABASE() 
                  AND table_name = 'paises' 
                  AND constraint_name = 'fk_paises_moneda');

SET @sqlstmt_fk := IF(@exist_fk > 0,
    'SELECT "Foreign key fk_paises_moneda already exists" AS message',
    'ALTER TABLE paises ADD CONSTRAINT fk_paises_moneda FOREIGN KEY (id_moneda_local) REFERENCES monedas(id)');

PREPARE stmt_fk FROM @sqlstmt_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- Verify changes
SELECT 
    p.id,
    p.nombre as pais,
    p.codigo_iso,
    p.id_moneda_local,
    m.codigo as moneda_codigo,
    m.nombre as moneda_nombre
FROM paises p
LEFT JOIN monedas m ON p.id_moneda_local = m.id
ORDER BY p.nombre;
