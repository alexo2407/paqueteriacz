-- Migración: Agregar campo bloqueado_edicion a tabla pedidos
-- Fecha: 2026-01-28
-- Descripción: Permite al admin bloquear/desbloquear la edición de pedidos por parte de proveedores

ALTER TABLE pedidos 
ADD COLUMN bloqueado_edicion TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Si es 1, solo admin puede editar el pedido. Si es 0, proveedor puede editar según reglas de negocio';

-- Crear índice para mejorar consultas
CREATE INDEX idx_bloqueado_edicion ON pedidos(bloqueado_edicion);
