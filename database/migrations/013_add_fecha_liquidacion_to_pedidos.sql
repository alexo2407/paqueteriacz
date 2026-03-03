-- Migration 013: Add fecha_liquidacion column to pedidos table
-- This date is recorded when an order is marked as "Entregado – liquidado" (ID 14)
ALTER TABLE pedidos
  ADD COLUMN fecha_liquidacion DATE NULL DEFAULT NULL AFTER fecha_entrega;
