-- =============================================================================
-- Rollback de Migración 021
-- =============================================================================

DROP TABLE IF EXISTS c807_municipios;
DROP TABLE IF EXISTS c807_departamentos;
DROP TABLE IF EXISTS forwarding_status_mapping;
DROP TABLE IF EXISTS forwarding_webhook_events;
DROP TABLE IF EXISTS forwarding_guias;

ALTER TABLE pedidos
    DROP COLUMN IF EXISTS correo,
    DROP COLUMN IF EXISTS peso,
    DROP COLUMN IF EXISTS unidad_peso,
    DROP COLUMN IF EXISTS bultos;
