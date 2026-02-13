-- Migration 008: Fix inverted cliente/proveedor columns
-- 
-- Problem: Columns are historically inverted
--   - id_cliente column stores proveedores (logistics companies)
--   - id_proveedor column stores clientes (order creators)
--
-- Solution: Swap column data to match semantic meaning
--   - id_cliente should store clientes (order creators)
--   - id_proveedor should store proveedores (logistics companies)

-- Step 1: Drop existing foreign keys
ALTER TABLE pedidos DROP FOREIGN KEY IF EXISTS pedidos_ibfk_4;
ALTER TABLE pedidos DROP FOREIGN KEY IF EXISTS fk_pedidos_cliente;
ALTER TABLE pedidos DROP FOREIGN KEY IF EXISTS fk_pedidos_proveedor;

-- Step 2: Create temporary columns
ALTER TABLE pedidos 
  ADD COLUMN id_cliente_temp INT NULL,
  ADD COLUMN id_proveedor_temp INT NULL;

-- Step 3: Copy data with swap (id_cliente ← id_proveedor, id_proveedor ← id_cliente)
UPDATE pedidos 
SET 
  id_cliente_temp = id_proveedor,
  id_proveedor_temp = id_cliente;

-- Step 4: Drop old columns
ALTER TABLE pedidos 
  DROP COLUMN id_cliente,
  DROP COLUMN id_proveedor;

-- Step 5: Rename temp columns to final names
ALTER TABLE pedidos 
  CHANGE COLUMN id_cliente_temp id_cliente INT NULL,
  CHANGE COLUMN id_proveedor_temp id_proveedor INT NULL;

-- Step 6: Add foreign key constraints back
ALTER TABLE pedidos
  ADD CONSTRAINT fk_pedidos_cliente 
    FOREIGN KEY (id_cliente) REFERENCES usuarios(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  ADD CONSTRAINT fk_pedidos_proveedor 
    FOREIGN KEY (id_proveedor) REFERENCES usuarios(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
