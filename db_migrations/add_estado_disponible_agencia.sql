-- db_migrations/add_estado_disponible_agencia.sql
-- Estado "Disponible para retirar en Agencia" (ID 19)
-- El paquete no pudo ser entregado en domicilio y está disponible en la agencia para retiro.
-- Semántica: EN PROCESO (el cliente aún puede retirar el paquete).
-- Categoría en informes: EN PROCESO (cae en el ELSE del CASE/WHEN de clasificación).
-- Fecha: 2026-08-10

INSERT IGNORE INTO estados_pedidos (id, nombre_estado)
VALUES (19, 'Disponible para retirar en Agencia');
