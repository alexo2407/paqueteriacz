-- Migration: Migrate legacy columns pedidos.producto/cantidad -> pedidos_productos pivot
-- Date: 2025-11-15
-- Safe, idempotent-ish SQL script. Review before running on production.

START TRANSACTION;

-- Insert into pedidos_productos for pedidos that still have producto (non-null/empty) and not already migrated
-- We try to resolve producto by exact name in productos; if not found we INSERT a new producto row.
-- Use user-defined variables to loop through rows; because pure-SQL looping is limited, we use INSERT ... SELECT

-- Create a temporary table with pedidos that look like legacy rows
CREATE TEMPORARY TABLE IF NOT EXISTS tmp_pedidos_legacy AS
SELECT id AS id_pedido, producto, cantidad FROM pedidos WHERE producto IS NOT NULL AND TRIM(producto) <> '' AND (SELECT COUNT(*) FROM pedidos_productos pp WHERE pp.id_pedido = pedidos.id) = 0;

-- For each distinct producto name in tmp, ensure it exists in productos
CREATE TEMPORARY TABLE IF NOT EXISTS tmp_productos_map AS
SELECT DISTINCT TRIM(producto) AS nombre_producto FROM tmp_pedidos_legacy;

-- Insert missing producto names into productos
INSERT INTO productos (nombre)
SELECT t.nombre_producto FROM tmp_productos_map t
LEFT JOIN productos p ON p.nombre = t.nombre_producto
WHERE p.id IS NULL;

-- Now insert mapping rows into pedidos_productos
INSERT INTO pedidos_productos (id_pedido, id_producto, cantidad, cantidad_devuelta)
SELECT
  tpl.id_pedido,
  p.id AS id_producto,
  COALESCE(tpl.cantidad, 1) AS cantidad,
  0
FROM tmp_pedidos_legacy tpl
INNER JOIN productos p ON p.nombre = TRIM(tpl.producto);

-- Optional: clear legacy columns (commented out by default). Uncomment after you verify data and backups.
-- ALTER TABLE pedidos DROP COLUMN producto, DROP COLUMN cantidad;

COMMIT;

-- NOTES:
-- 1) Run a full backup before executing this script:
--    mysqldump -u user -p --single-transaction --routines --triggers --databases your_db > backup.sql
-- 2) Review inserted rows:
--    SELECT * FROM pedidos_productos WHERE id_pedido IN (SELECT id_pedido FROM tmp_pedidos_legacy);
-- 3) The script inserts new productos with only the name set; consider populating descripcion/precio_usd later.
