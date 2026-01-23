-- Migración: Agregar soporte para combos de productos
-- Fecha: 2026-01-20
-- Descripción: Agrega campos necesarios para manejar combos de productos
--              donde el proveedor define un precio único en su moneda local

USE paquetes_apppack;

-- 1. Agregar moneda local a tabla usuarios (proveedores)
ALTER TABLE usuarios 
  ADD COLUMN id_moneda_local INT(11) NULL AFTER id_pais,
  ADD CONSTRAINT fk_usuarios_moneda 
    FOREIGN KEY (id_moneda_local) REFERENCES monedas(id) 
    ON DELETE SET NULL;

-- Índice para mejorar consultas
CREATE INDEX idx_usuarios_moneda ON usuarios(id_moneda_local);

-- 2. Agregar columnas para combos en la tabla pedidos
ALTER TABLE pedidos 
  ADD COLUMN es_combo TINYINT(1) DEFAULT 0 AFTER total_usd,
  ADD COLUMN precio_local DECIMAL(10,2) NULL AFTER es_combo,
  ADD COLUMN observaciones_combo TEXT NULL AFTER precio_local;

-- Índice para mejorar consultas de combos
CREATE INDEX idx_pedidos_es_combo ON pedidos(es_combo);

-- 3. Comentarios para documentación
ALTER TABLE usuarios
  MODIFY COLUMN id_moneda_local INT(11) NULL COMMENT 'Moneda local del proveedor para combos';

ALTER TABLE pedidos 
  MODIFY COLUMN es_combo TINYINT(1) DEFAULT 0 COMMENT 'Flag que indica si el pedido es un combo (1) o no (0)',
  MODIFY COLUMN precio_local DECIMAL(10,2) NULL COMMENT 'Precio total en moneda local del proveedor',
  MODIFY COLUMN observaciones_combo TEXT NULL COMMENT 'Notas adicionales sobre el combo';
