-- ============================================================
-- Migración: Agregar columnas de LogisPro a tabla pedidos
-- Ejecutar si las columnas NO existen en la BD
-- ============================================================

-- Agregar columna municipalitiesName si no existe
ALTER TABLE `pedidos`
    ADD COLUMN IF NOT EXISTS `municipalitiesName` VARCHAR(255) DEFAULT NULL COMMENT 'Nombre del municipio para API LogisPro',
    ADD COLUMN IF NOT EXISTS `postalCode`         INT          DEFAULT NULL COMMENT 'Código postal (entero) para API LogisPro',
    ADD COLUMN IF NOT EXISTS `departmentName`     VARCHAR(255) DEFAULT NULL COMMENT 'Nombre del departamento para API LogisPro',
    ADD COLUMN IF NOT EXISTS `Location`           VARCHAR(255) DEFAULT NULL COMMENT 'Barrio/Colonia para API LogisPro (se puebla desde campo barrio del XLSX)',
    ADD COLUMN IF NOT EXISTS `betweenStreets`     VARCHAR(255) DEFAULT NULL COMMENT 'Entre calles para API LogisPro';
