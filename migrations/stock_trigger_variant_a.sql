-- Variant A: for stock(id_vendedor, id_producto, cantidad)
-- Inserts a negative movement when a pedidos_productos row is inserted.
-- Ensure this matches your schema before applying.

DELIMITER $$
CREATE TRIGGER trg_pedidos_productos_after_insert_a
AFTER INSERT ON pedidos_productos
FOR EACH ROW
BEGIN
  DECLARE v_vendedor INT;
  -- Obtener id_vendedor del pedido padre
  SELECT id_vendedor INTO v_vendedor FROM pedidos WHERE id = NEW.id_pedido LIMIT 1;

  INSERT INTO stock (id_vendedor, id_producto, cantidad)
  VALUES (IFNULL(v_vendedor, 0), NEW.id_producto, -NEW.cantidad);
END $$
DELIMITER ;
