-- =====================================================
-- Migración: Intercambiar nombres de roles Proveedor-Cliente
-- =====================================================
-- Fecha: 2026-01-30
-- Descripción: Intercambia los nombres de los roles ID 4 y ID 5
--              para corregir la asignación de permisos sin modificar código.
--
-- ANTES:
--   ID 4: "Proveedor" (verifica id_proveedor en pedidos)
--   ID 5: "Cliente" (verifica id_cliente en pedidos)
--
-- DESPUÉS:
--   ID 4: "Cliente" (verifica id_proveedor en pedidos) ✓ CORRECTO
--   ID 5: "Proveedor" (verifica id_cliente en pedidos) ✓ CORRECTO
-- =====================================================

-- Usar una transacción para garantizar atomicidad
START TRANSACTION;

-- Paso 1: Renombrar temporalmente para evitar conflictos de unique constraint
UPDATE roles 
SET nombre_rol = 'Proveedor_TEMP' 
WHERE id = 4 AND nombre_rol = 'Proveedor';

UPDATE roles 
SET nombre_rol = 'Cliente_TEMP' 
WHERE id = 5 AND nombre_rol = 'Cliente';

-- Paso 2: Aplicar los nombres finales intercambiados
UPDATE roles 
SET nombre_rol = 'Cliente' 
WHERE id = 4 AND nombre_rol = 'Proveedor_TEMP';

UPDATE roles 
SET nombre_rol = 'Proveedor' 
WHERE id = 5 AND nombre_rol = 'Cliente_TEMP';

-- Verificar cambios
SELECT id, nombre_rol, descripcion 
FROM roles 
WHERE id IN (4, 5)
ORDER BY id;

-- Si todo está correcto, hacer commit
COMMIT;

-- Para revertir (ejecutar solo si es necesario):
-- START TRANSACTION;
-- UPDATE roles SET nombre_rol = 'Proveedor_TEMP' WHERE id = 4 AND nombre_rol = 'Cliente';
-- UPDATE roles SET nombre_rol = 'Cliente_TEMP' WHERE id = 5 AND nombre_rol = 'Proveedor';
-- UPDATE roles SET nombre_rol = 'Proveedor' WHERE id = 4 AND nombre_rol = 'Proveedor_TEMP';
-- UPDATE roles SET nombre_rol = 'Cliente' WHERE id = 5 AND nombre_rol = 'Cliente_TEMP';
-- COMMIT;
