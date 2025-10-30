-- Variant B: for stock(id_usuario, producto, cantidad)
-- Maps pedido.id_vendedor -> id_usuario and writes producto as the product id.

DELIMITER $$
CREATE TRIGGER trg_pedidos_productos_after_insert_b
AFTER INSERT ON pedidos_productos
FOR EACH ROW
BEGIN
  DECLARE v_vendedor INT;
  SELECT id_vendedor INTO v_vendedor FROM pedidos WHERE id = NEW.id_pedido LIMIT 1;

  INSERT INTO stock (id_usuario, producto, cantidad)
  VALUES (IFNULL(v_vendedor, 0), NEW.id_producto, -NEW.cantidad);
END $$
DELIMITER ;
