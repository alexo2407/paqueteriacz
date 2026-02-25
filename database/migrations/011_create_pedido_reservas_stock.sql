-- Migration: tabla de reservas de stock por pedido
-- Archivo: 011_create_pedido_reservas_stock.sql

CREATE TABLE IF NOT EXISTS pedido_reservas_stock (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido       INT NOT NULL,
    id_producto     INT NOT NULL,
    cantidad        INT NOT NULL DEFAULT 0,
    liberada        TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pedido_producto (id_pedido, id_producto),
    KEY idx_pedido  (id_pedido),
    KEY idx_producto (id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Control de idempotencia para reservas de stock por pedido';
