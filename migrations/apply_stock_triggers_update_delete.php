<?php
require_once __DIR__ . '/../config/config.php';

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_SCHEMA);
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
    $db = new PDO($dsn, DB_USER, DB_PASSWORD, $opts);

    // Drop existing triggers if any
    $db->exec("DROP TRIGGER IF EXISTS `trg_pedidos_productos_after_update`");
    $db->exec("DROP TRIGGER IF EXISTS `trg_pedidos_productos_after_delete`");

    $sqlUpdate = <<<SQL
CREATE TRIGGER `trg_pedidos_productos_after_update`
AFTER UPDATE ON `pedidos_productos`
FOR EACH ROW
BEGIN
  DECLARE user_stock INT DEFAULT 0;
  DECLARE diff INT;

  SELECT COALESCE(id_proveedor, id_vendedor, 0) INTO user_stock
  FROM pedidos
  WHERE id = NEW.id_pedido
  LIMIT 1;

  IF OLD.id_producto <> NEW.id_producto THEN
    -- Restaurar stock del producto antiguo
    UPDATE stock
    SET cantidad = cantidad + OLD.cantidad
    WHERE id_producto = OLD.id_producto
      AND id_usuario = user_stock;
    IF ROW_COUNT() = 0 THEN
      INSERT INTO stock (id_usuario, id_producto, cantidad)
      VALUES (user_stock, OLD.id_producto, OLD.cantidad);
    END IF;

    -- Aplicar salida para el nuevo producto
    UPDATE stock
    SET cantidad = cantidad - NEW.cantidad
    WHERE id_producto = NEW.id_producto
      AND id_usuario = user_stock;
    IF ROW_COUNT() = 0 THEN
      INSERT INTO stock (id_usuario, id_producto, cantidad)
      VALUES (user_stock, NEW.id_producto, -NEW.cantidad);
    END IF;
  ELSE
    SET diff = NEW.cantidad - OLD.cantidad;
    IF diff <> 0 THEN
      UPDATE stock
      SET cantidad = cantidad - diff
      WHERE id_producto = NEW.id_producto
        AND id_usuario = user_stock;
      IF ROW_COUNT() = 0 THEN
        INSERT INTO stock (id_usuario, id_producto, cantidad)
        VALUES (user_stock, NEW.id_producto, -diff);
      END IF;
    END IF;
  END IF;
END;
SQL;

    $sqlDelete = <<<SQL
CREATE TRIGGER `trg_pedidos_productos_after_delete`
AFTER DELETE ON `pedidos_productos`
FOR EACH ROW
BEGIN
  DECLARE user_stock INT DEFAULT 0;

  SELECT COALESCE(id_proveedor, id_vendedor, 0) INTO user_stock
  FROM pedidos
  WHERE id = OLD.id_pedido
  LIMIT 1;

  UPDATE stock
  SET cantidad = cantidad + OLD.cantidad
  WHERE id_producto = OLD.id_producto
    AND id_usuario = user_stock;

  IF ROW_COUNT() = 0 THEN
    INSERT INTO stock (id_usuario, id_producto, cantidad)
    VALUES (user_stock, OLD.id_producto, OLD.cantidad);
  END IF;
END;
SQL;

    $db->exec($sqlUpdate);
    $db->exec($sqlDelete);

    echo "Triggers AFTER UPDATE and AFTER DELETE creados correctamente.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Error al crear triggers: " . $e->getMessage() . "\n");
    exit(1);
}
