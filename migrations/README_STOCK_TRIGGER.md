This folder contains SQL trigger examples to keep product stock consistent when orders are created.

Overview
--------
The application treats the `stock` table as a ledger of movements: positive quantities increment stock and negative quantities decrement it. To centralize stock adjustments in the database we provide trigger examples that insert a negative movement into `stock` whenever a row is inserted into `pedidos_productos`.

Before applying any trigger:
- Inspect your `stock` table schema to pick the correct trigger variant. Run:

  SHOW COLUMNS FROM stock;

- Inspect `pedidos`/`pedidos_productos` to confirm column names (`id_vendedor` in `pedidos` and `id_pedido` in `pedidos_productos`).

Trigger variants
----------------
1) Variant A — stock has columns: `id_vendedor`, `id_producto`, `cantidad`
   - This trigger inserts (id_vendedor, id_producto, cantidad = -NEW.cantidad)

2) Variant B — stock has columns: `id_usuario`, `producto`, `cantidad`
   - This trigger looks up the pedido's `id_vendedor` and writes it into `id_usuario` (or uses 0 if null), and writes `producto` as NEW.id_producto

How to apply
------------
From a shell (mysql client):

mysql -u <user> -p <database> < migrations/stock_trigger_variant_a.sql

Or paste the chosen SQL into phpMyAdmin / Adminer and execute.

Notes
-----
- Triggers will run inside the same transaction as the INSERT performed by the application, so any failure in the trigger will rollback the transaction.
- Make a DB backup before applying triggers on production.
- If your `stock` table uses a different schema, adapt one of the variants accordingly or share the schema and I can provide a custom trigger.
