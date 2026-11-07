-- =============================================================================
-- Migración 023: Estado 'Devolución' en estados_pedidos y mapeo C807 código 17
-- =============================================================================

-- 1. Asegurar que exista el estado 'Devolución' en estados_pedidos
INSERT INTO estados_pedidos (nombre_estado, descripcion)
SELECT 'Devolución', 'Paquete en proceso de devolución reportado por operador logístico'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM estados_pedidos WHERE nombre_estado = 'Devolución'
);

-- 2. Registrar código 17 (Devolución) en forwarding_status_mapping para c807
SET @id_estado_devolucion := (
    SELECT id FROM estados_pedidos WHERE nombre_estado = 'Devolución' LIMIT 1
);

INSERT INTO forwarding_status_mapping
    (provider_slug, codigo_externo, razon_codigo, descripcion_externa, id_estado_interno, es_confirmado, activo)
SELECT 'c807', '17', NULL, 'Devolución', COALESCE(@id_estado_devolucion, 7), 1, 1
FROM DUAL
WHERE @id_estado_devolucion IS NOT NULL
ON DUPLICATE KEY UPDATE
    descripcion_externa = VALUES(descripcion_externa),
    id_estado_interno   = VALUES(id_estado_interno);
