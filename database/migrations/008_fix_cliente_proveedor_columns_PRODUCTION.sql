-- ============================================================================
-- MIGRACIÓN PARA PRODUCCIÓN: Fix Cliente/Proveedor Columns
-- ============================================================================
-- 
-- IMPORTANTE: Esta migración intercambia los datos en las columnas id_cliente
-- e id_proveedor para corregir una inversión histórica en la base de datos.
--
-- ANTES de ejecutar:
-- 1. Hacer backup completo de la base de datos
-- 2. Verificar que no haya procesos activos modificando pedidos
-- 3. Poner la aplicación en modo mantenimiento si es posible
--
-- DESPUÉS de ejecutar:
-- 1. Verificar que los datos se intercambiaron correctamente
-- 2. Desplegar el código actualizado del controlador (sin SWAP logic)
-- 3. Probar creación y edición de pedidos
-- ============================================================================

-- Paso 1: Eliminar foreign keys existentes
ALTER TABLE pedidos DROP FOREIGN KEY IF EXISTS pedidos_ibfk_4;
ALTER TABLE pedidos DROP FOREIGN KEY IF EXISTS fk_pedidos_cliente;
ALTER TABLE pedidos DROP FOREIGN KEY IF EXISTS fk_pedidos_proveedor;

-- Paso 2: Crear columnas temporales para el intercambio
ALTER TABLE pedidos 
  ADD COLUMN id_cliente_temp INT NULL,
  ADD COLUMN id_proveedor_temp INT NULL;

-- Paso 3: Copiar datos intercambiados a columnas temporales
-- id_cliente_temp ← id_proveedor (lo que estaba en proveedor va a cliente)
-- id_proveedor_temp ← id_cliente (lo que estaba en cliente va a proveedor)
UPDATE pedidos 
SET 
  id_cliente_temp = id_proveedor,
  id_proveedor_temp = id_cliente;

-- Paso 4: Actualizar columnas originales con datos intercambiados
UPDATE pedidos 
SET 
  id_cliente = id_cliente_temp,
  id_proveedor = id_proveedor_temp;

-- Paso 5: Eliminar columnas temporales
ALTER TABLE pedidos 
  DROP COLUMN id_cliente_temp,
  DROP COLUMN id_proveedor_temp;

-- Paso 6: Recrear foreign keys
ALTER TABLE pedidos
  ADD CONSTRAINT fk_pedidos_cliente 
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  ADD CONSTRAINT fk_pedidos_proveedor 
    FOREIGN KEY (id_proveedor) REFERENCES usuarios(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

-- Verificación: Mostrar algunos registros para confirmar el intercambio
SELECT 
    p.id, 
    p.numero_orden, 
    p.id_cliente, 
    p.id_proveedor,
    uc.nombre as cliente_nombre,
    up.nombre as proveedor_nombre
FROM pedidos p
LEFT JOIN usuarios uc ON p.id_cliente = uc.id
LEFT JOIN usuarios up ON p.id_proveedor = up.id
ORDER BY p.id DESC
LIMIT 10;
