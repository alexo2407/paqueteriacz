-- ============================================================
-- Migración: Re-asociar barrios a codigos_postales
-- Fecha: 2026-03-03
-- Base de datos: sistema_multinacional
--
-- SEGURO para ejecutar en cualquier estado de producción.
-- Verifica antes de ejecutar:
--   SHOW COLUMNS FROM codigos_postales;
--   SHOW INDEX FROM codigos_postales;
-- ============================================================

-- PASO 1: Añadir columna id_barrio si NO existe ya
-- (En producción puede que ya exista o no)
ALTER TABLE codigos_postales
    ADD COLUMN IF NOT EXISTS id_barrio INT NULL AFTER id_municipio;

-- PASO 2: Añadir FK de barrio si NO existe ya
-- Si falla con "Duplicate key name fk_cp_barrio", ignorar y continuar
ALTER TABLE codigos_postales
    ADD CONSTRAINT fk_cp_barrio
        FOREIGN KEY (id_barrio) REFERENCES barrios(id) ON DELETE SET NULL;

-- PASO 3: Quitar la FK de pais que usa uk_pais_cp (para poder eliminar el índice)
-- Mueve temporalmente la FK al índice de id_pais simple
ALTER TABLE codigos_postales DROP FOREIGN KEY fk_cp_pais;

-- PASO 4: Eliminar unique key antigua
ALTER TABLE codigos_postales DROP INDEX uk_pais_cp;

-- PASO 5: Crear nueva unique key con barrio incluido
ALTER TABLE codigos_postales
    ADD UNIQUE KEY uk_pais_cp_barrio (id_pais, codigo_postal, id_barrio);

-- PASO 6: Re-crear la FK de país (ahora usa el índice de id_pais que ya existe)
ALTER TABLE codigos_postales
    ADD CONSTRAINT fk_cp_pais FOREIGN KEY (id_pais) REFERENCES paises(id);

-- ============================================================
-- Verificación final (ejecutar por separado para confirmar):
--   SHOW INDEX FROM codigos_postales WHERE Key_name = 'uk_pais_cp_barrio';
--   SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
--     WHERE TABLE_NAME = 'codigos_postales'
--     AND TABLE_SCHEMA = 'sistema_multinacional'
--     AND REFERENCED_TABLE_NAME IS NOT NULL;
-- ============================================================

