-- ============================================================
-- Migración: campo bandera `requiere_productos` en usuarios
-- Permite que clientes específicos creen pedidos sin productos
-- ============================================================
ALTER TABLE usuarios
    ADD COLUMN requiere_productos TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1 = el cliente debe enviar productos al crear pedido; 0 = productos opcionales';
