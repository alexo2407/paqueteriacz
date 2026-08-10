-- db_migrations/add_estado_correo.sql
-- Estado "Correo" (ID 18) — Solicitado por cliente para pedidos enviados vía correo postal.
-- El paquete fue despachado por servicio de correo, pero la entrega aún no se confirma.
-- Semántica: EN PROCESO (tránsito postal). Color: CLR_LOGISTICA (azul #3498db).
-- Fecha: 2026-08-03
-- Nota: Este estado es específico del cliente y NO aplica al mapeo del webhook RutaEx.

INSERT IGNORE INTO estados_pedidos (id, nombre_estado)
VALUES (18, 'Correo');
