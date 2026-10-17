-- Migración 026: Agregar columnas operativas de Hub/Estación C807 a c807_municipios
-- Basado en el catálogo oficial de C807 Express (Base de Hub y departamentos)

ALTER TABLE `c807_municipios` 
  ADD COLUMN `codigo_estacion` VARCHAR(10) NULL COMMENT 'Código de Estación/Hub (ej. HAHU, PLL, HTOR)' AFTER `nombre`,
  ADD COLUMN `estacion` VARCHAR(100) NULL COMMENT 'Nombre de la Estación/Hub (ej. Hub Ahuachapan, HUB PLAN DE LA LAGUNA)' AFTER `codigo_estacion`,
  ADD COLUMN `codigo_distrito` VARCHAR(100) NULL COMMENT 'Código oficial del distrito C807' AFTER `estacion`;
