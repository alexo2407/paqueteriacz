-- Script para generar SKUs automáticos para productos sin SKU
-- Este script actualiza todos los productos que tienen SKU NULL o vacío
-- generando SKUs automáticos basados en su categoría

-- Backup de productos antes de la actualización
-- (Descomentar si quieres crear una tabla de respaldo)
-- CREATE TABLE productos_backup_sku AS SELECT * FROM productos WHERE sku IS NULL OR sku = '';

-- Actualizar productos sin SKU
-- Nota: Este script usa una lógica simple de numeración secuencial
-- Si prefieres números aleatorios, puedes usar RAND() en lugar de id

-- Para productos con categoría
UPDATE productos p
LEFT JOIN categorias c ON p.categoria_id = c.id
SET p.sku = CONCAT(
    UPPER(SUBSTRING(COALESCE(c.nombre, 'PROD'), 1, 4)),
    '-',
    LPAD(p.id, 3, '0')
)
WHERE (p.sku IS NULL OR p.sku = '')
AND p.categoria_id IS NOT NULL;

-- Para productos sin categoría
UPDATE productos
SET sku = CONCAT('PROD-', LPAD(id, 3, '0'))
WHERE (sku IS NULL OR sku = '')
AND categoria_id IS NULL;

-- Verificar productos actualizados
SELECT 
    id,
    nombre,
    sku,
    categoria_id,
    CASE 
        WHEN categoria_id IS NOT NULL THEN 'Con categoría'
        ELSE 'Sin categoría'
    END as tipo
FROM productos
ORDER BY id;

-- Verificar si quedan productos sin SKU
SELECT COUNT(*) as productos_sin_sku
FROM productos
WHERE sku IS NULL OR sku = '';
