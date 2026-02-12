-- Migration: Change numero_orden to BIGINT to support larger order numbers
-- Date: 2026-02-12
-- Issue: SQLSTATE[22003]: Numeric value out of range for numero_orden

ALTER TABLE pedidos MODIFY COLUMN numero_orden BIGINT NOT NULL;
