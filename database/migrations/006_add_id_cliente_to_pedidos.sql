-- Agregar columna id_cliente a la tabla pedidos
-- Esta columna permite asignar un usuario con rol 'Cliente' a una orden.

ALTER TABLE pedidos ADD COLUMN id_cliente INT(11) NULL DEFAULT NULL AFTER id_proveedor;
