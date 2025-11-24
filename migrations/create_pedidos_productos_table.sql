CREATE TABLE IF NOT EXISTS `pedidos_productos` (
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `cantidad_devuelta` int(11) DEFAULT '0',
  PRIMARY KEY (`id_pedido`,`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
