-- =============================================================================
-- Migración 028: Integración de Proveedor ShipEdge (OMS API v41)
-- =============================================================================
-- Incluye:
-- 1. Registro inicial del proveedor ShipEdge (desactivado hasta configurar credenciales).
-- 2. Plantilla inicial configurable de campos y mapeos v41 (forwarding_api_fields / forwarding_api_mappings).
-- 3. Homologación inicial de estados externos de ShipEdge (forwarding_status_mapping).
--    IMPORTANTE: 'shipped' se mapea a 'En ruta o proceso' (ID 2), NUNCA a 'Entregado' (ID 3).
-- =============================================================================

-- 1. Insertar proveedor ShipEdge (si no existe)
-- Nota: No se almacenan credenciales reales en código ni migraciones.
INSERT INTO forwarding_providers
    (nombre, slug, base_url, auth_method, credentials, activo)
SELECT
    'ShipEdge OMS v41',
    'shipedge',
    'https://mlacostarica.shipedge.com/apirest/v41/oms',
    'api_key',
    '{"account_id":"","api_key":"","shipping_method":"","carrier_name":"","store_channel":"","default_country":"","currency_code":"","enable_sold_price":false,"webhook_secret":""}',
    0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM forwarding_providers WHERE slug = 'shipedge'
);

SET @shipedge_id = (SELECT id FROM forwarding_providers WHERE slug = 'shipedge' ORDER BY id ASC LIMIT 1);

-- 2. Plantilla inicial de campos y mapeos de ShipEdge v41 (POST /orders -> data[])
-- Campo: order_number (Obligatorio)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'order_number', 'Número único de orden (order_number)', 'string', 1, NULL, 10
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'order_number'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'numero_orden', 'Texto exacto (sin alterar en reintentos)'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'order_number'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: shipping_method (Obligatorio, desde configuración de proveedor/regla)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'shipping_method', 'Método de envío configurado (shipping_method)', 'string', 1, NULL, 20
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'shipping_method'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, '_config_shipping_method', 'Desde configuración de cuenta o regla'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'shipping_method'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: customer.firstname (Obligatorio, permite nombre completo)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'customer.firstname', 'Nombre completo del destinatario (customer.firstname)', 'string', 1, NULL, 30
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.firstname'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'destinatario', 'Sin transformación'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.firstname'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: customer.address1 (Obligatorio)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'customer.address1', 'Dirección principal (customer.address1)', 'string', 1, NULL, 40
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.address1'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'direccion', 'Sin transformación'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.address1'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: customer.address2 (Opcional, referencias / betweenStreets)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'customer.address2', 'Dirección secundaria / Entre calles (customer.address2)', 'string', 0, NULL, 50
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.address2'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'betweenStreets', 'Opcional si existe'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.address2'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: customer.city (Obligatorio, prioridad: municipio_nombre -> municipalitiesName)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'customer.city', 'Municipio / Ciudad (customer.city)', 'string', 1, NULL, 60
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.city'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'municipio_nombre|municipalitiesName', 'Prioridad: municipio_nombre -> municipalitiesName'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.city'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: customer.state (Obligatorio, prioridad: departamento -> departmentName)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'customer.state', 'Departamento / Provincia / Estado (customer.state)', 'string', 1, NULL, 70
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.state'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'departamento|departmentName', 'Prioridad: departamento -> departmentName'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.state'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: customer.zip (Obligatorio, prioridad: codigo_postal -> postalCode, preserva ceros iniciales)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'customer.zip', 'Código Postal como texto (customer.zip)', 'string', 1, NULL, 80
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.zip'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'codigo_postal|postalCode', 'preserve_leading_zeros'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.zip'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: customer.country (Obligatorio, prioridad: pais_codigo_iso -> pais_nombre -> _config_default_country)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'customer.country', 'País de destino (customer.country)', 'string', 1, NULL, 90
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.country'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'pais_codigo_iso|pais_nombre|_config_default_country', 'Prioridad: ISO país -> Nombre país -> País config'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.country'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: customer.email (Opcional, cuando exista)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'customer.email', 'Correo electrónico (customer.email)', 'string', 0, NULL, 100
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.email'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'correo', 'Opcional si existe'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.email'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: customer.phone (Opcional, cuando exista)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'customer.phone', 'Teléfono del destinatario (customer.phone)', 'string', 0, NULL, 110
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.phone'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'telefono', 'Opcional si existe'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'customer.phone'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: items[].sku (Obligatorio por producto)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'items[].sku', 'SKU del Producto (items[].sku)', 'string', 1, NULL, 120
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'items[].sku'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'productos.sku', 'Sin transformación'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'items[].sku'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: items[].quantity (Obligatorio por producto)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'items[].quantity', 'Cantidad del Producto (items[].quantity)', 'int', 1, NULL, 130
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'items[].quantity'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'productos.cantidad', 'Entero > 0'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'items[].quantity'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- Campo: items[].sold_price (Opcional, solo tras validar moneda confirmada; NUNCA deriva de precio_total_local)
INSERT INTO forwarding_api_fields
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @shipedge_id, 'items[].sold_price', 'Precio unitario validado (items[].sold_price)', 'float', 0, NULL, 140
WHERE @shipedge_id IS NOT NULL AND NOT EXISTS (
    SELECT 1 FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'items[].sold_price'
);

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'productos.precio_unitario', 'validate_currency'
FROM forwarding_api_fields WHERE id_provider = @shipedge_id AND field_path = 'items[].sold_price'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

-- 3. Homologación inicial de estados externos de ShipEdge -> estados internos
-- Estados internos base:
--   1 = En bodega
--   2 = En ruta o proceso
--   3 = Entregado
--   4 = Con incidencia
--   6 = Cancelado
--   7 = Rechazado por cliente
--  12 = Recolectado por mensajería
SET @id_estado_devolucion := (
    SELECT id FROM estados_pedidos WHERE nombre_estado = 'Devolución' LIMIT 1
);

INSERT INTO forwarding_status_mapping
    (provider_slug, codigo_externo, razon_codigo, descripcion_externa, id_estado_interno, es_confirmado, activo)
VALUES
    ('shipedge', 'pending',        NULL, 'Orden recibida / Pendiente en OMS',                    1,  0, 1),
    ('shipedge', 'processing',     NULL, 'En proceso de preparación / Picking en almacén',       1,  0, 1),
    ('shipedge', 'in_process',     NULL, 'En proceso en almacén (ShipEdge OMS)',                 1,  0, 1),
    ('shipedge', 'ready_to_ship',  NULL, 'Empacado / Listo para despacho',                      12,  0, 1),
    ('shipedge', 'packed',         NULL, 'Empacado en almacén',                                 12,  0, 1),
    ('shipedge', 'shipped',        NULL, 'Despachado de almacén (En ruta — NO es Entregado)',    2,  1, 1),
    ('shipedge', 'in_transit',     NULL, 'En tránsito con transportista',                        2,  0, 1),
    ('shipedge', 'delivered',      NULL, 'Entregado confirmado por transportista (por validar)', 3,  0, 1),
    ('shipedge', 'on_hold',        NULL, 'Orden retenida / En espera (On Hold)',                 4,  0, 1),
    ('shipedge', 'backorder',      NULL, 'Sin stock / Backorder en almacén',                     4,  0, 1),
    ('shipedge', 'cancelled',      NULL, 'Orden cancelada en ShipEdge',                          6,  0, 1),
    ('shipedge', 'canceled',       NULL, 'Orden cancelada en ShipEdge (US spelling)',            6,  0, 1),
    ('shipedge', 'returned',       NULL, 'Devolución reportada en ShipEdge', COALESCE(@id_estado_devolucion, 7), 0, 1)
ON DUPLICATE KEY UPDATE
    descripcion_externa = VALUES(descripcion_externa),
    id_estado_interno   = VALUES(id_estado_interno);

