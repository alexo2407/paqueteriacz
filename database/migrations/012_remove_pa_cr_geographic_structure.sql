-- SQL Migration: Remove Geographic Structure for Costa Rica and Panama
-- This script safely removes postal codes and geographic hierarchy while maintaining order integrity.

-- 1. Nullify geographic references in existing orders for these countries
-- This avoids foreign key constraint violations.
UPDATE pedidos 
SET id_codigo_postal = NULL, 
    id_barrio = NULL, 
    id_municipio = NULL, 
    id_departamento = NULL 
WHERE id_pais IN (SELECT id FROM paises WHERE nombre IN ('Costa Rica', 'Panamá'));

-- 2. Delete Postal Codes
DELETE FROM codigos_postales 
WHERE id_pais IN (SELECT id FROM paises WHERE nombre IN ('Nicaragua', 'Panamá'));

-- 3. Delete Neighborhoods (Barrios)
DELETE FROM barrios 
WHERE id_municipio IN (
    SELECT m.id 
    FROM municipios m 
    JOIN departamentos d ON m.id_departamento = d.id 
    WHERE d.id_pais IN (SELECT id FROM paises WHERE nombre IN ('Nicaragua', 'Panamá'))
);

-- 4. Delete Municipalities (Municipios)
DELETE FROM municipios 
WHERE id_departamento IN (
    SELECT id 
    FROM departamentos 
    WHERE id_pais IN (SELECT id FROM paises WHERE nombre IN ('Nicaragua', 'Panamá'))
);

-- 5. Delete Departments (Departamentos)
DELETE FROM departamentos 
WHERE id_pais IN (SELECT id FROM paises WHERE nombre IN ('Nicaragua', 'Panamá'));
