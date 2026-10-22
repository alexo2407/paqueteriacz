-- =============================================================================
-- Migración 027: Habilitar visibilidad de courier para cuentas EconGlobal
-- (Guatemala, Panamá, Uruguay)
-- =============================================================================

UPDATE usuarios 
SET mostrar_courier = 1 
WHERE id IN (10, 21, 26, 39, 52, 53)
   OR nombre LIKE '%econglobal%'
   OR email LIKE '%econglobal%';
