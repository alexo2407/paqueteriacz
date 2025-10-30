<?php
require_once __DIR__ . '/../config/config.php';

// Ejecuta la variante 1 del trigger que actualiza o inserta fila en stock
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_SCHEMA);
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $db = new PDO($dsn, DB_USER, DB_PASSWORD, $opts);

    // Nombre del trigger
    $triggerName = 'descontar_stock_al_insertar_producto';

    // Drop si existe
    $db->exec("DROP TRIGGER IF EXISTS `" . $triggerName . "`");

    $create = <<<SQL
CREATE TRIGGER `descontar_stock_al_insertar_producto`
AFTER INSERT ON `pedidos_productos`
FOR EACH ROW
BEGIN
  DECLARE user_stock INT DEFAULT 0;
  -- Obtener id_proveedor o id_vendedor; si son NULL usar 0
  SELECT COALESCE(id_proveedor, id_vendedor, 0) INTO user_stock
  FROM pedidos
  WHERE id = NEW.id_pedido
  LIMIT 1;

  -- Intentar actualizar una fila agregada de stock
  UPDATE stock
  SET cantidad = cantidad - NEW.cantidad
  WHERE id_producto = NEW.id_producto
    AND id_usuario = user_stock;

  -- Si no había fila agregada, crear una (cantidad negativa)
  IF ROW_COUNT() = 0 THEN
    INSERT INTO stock (id_usuario, id_producto, cantidad)
    VALUES (user_stock, NEW.id_producto, -NEW.cantidad);
  END IF;
END;
SQL;

    $db->exec($create);

    echo "Trigger '{$triggerName}' creado correctamente.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Error al crear trigger: " . $e->getMessage() . "\n");
    exit(1);
}
