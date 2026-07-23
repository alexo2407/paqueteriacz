-- =====================================================================
-- MIGRACIÓN: Corregir columna code_city en tabla pedidos
-- Problema: code_city definida como NOT NULL sin DEFAULT en producción,
--           causando SQLSTATE[23000] al crear pedidos sin ese campo.
-- Solución: Permitir NULL (o establecer DEFAULT '').
-- =====================================================================

-- Opción A (recomendada): permitir NULL
ALTER TABLE pedidos
    MODIFY COLUMN code_city VARCHAR(50) NULL DEFAULT NULL
    COMMENT 'Código de ciudad para HLExpress (city_dane_code). Opcional.';

-- Opción B (alternativa): mantener NOT NULL con DEFAULT vacío
-- ALTER TABLE pedidos
--     MODIFY COLUMN code_city VARCHAR(50) NOT NULL DEFAULT ''
--     COMMENT 'Código de ciudad para HLExpress (city_dane_code). Opcional.';
