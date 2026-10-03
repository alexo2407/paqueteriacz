-- =============================================================================
-- Migración 021: Soporte para integración de proveedor C807 Xpress y mejoras de Forwarding
-- =============================================================================

-- 1. Nuevas columnas en tabla pedidos para datos de empaque y contacto (compatible MySQL 5.7 / 8.0 / MariaDB)

-- Columna correo
SET @exist_correo := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'correo');
SET @sqlstmt_correo := IF(@exist_correo > 0, 'DO 0', 'ALTER TABLE pedidos ADD COLUMN correo VARCHAR(150) NULL DEFAULT NULL COMMENT \'Correo electrónico del destinatario\'');
PREPARE stmt_correo FROM @sqlstmt_correo;
EXECUTE stmt_correo;
DEALLOCATE PREPARE stmt_correo;

-- Columna peso
SET @exist_peso := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'peso');
SET @sqlstmt_peso := IF(@exist_peso > 0, 'DO 0', 'ALTER TABLE pedidos ADD COLUMN peso DECIMAL(10,2) NULL DEFAULT NULL COMMENT \'Peso físico real para envíos\'');
PREPARE stmt_peso FROM @sqlstmt_peso;
EXECUTE stmt_peso;
DEALLOCATE PREPARE stmt_peso;

-- Columna unidad_peso
SET @exist_upeso := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'unidad_peso');
SET @sqlstmt_upeso := IF(@exist_upeso > 0, 'DO 0', 'ALTER TABLE pedidos ADD COLUMN unidad_peso VARCHAR(10) NULL DEFAULT NULL COMMENT \'Unidad de peso (LB, KG)\'');
PREPARE stmt_upeso FROM @sqlstmt_upeso;
EXECUTE stmt_upeso;
DEALLOCATE PREPARE stmt_upeso;

-- Columna bultos
SET @exist_bultos := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'pedidos' AND column_name = 'bultos');
SET @sqlstmt_bultos := IF(@exist_bultos > 0, 'DO 0', 'ALTER TABLE pedidos ADD COLUMN bultos INT NULL DEFAULT 1 COMMENT \'Cantidad de paquetes/bultos físicos\'');
PREPARE stmt_bultos FROM @sqlstmt_bultos;
EXECUTE stmt_bultos;
DEALLOCATE PREPARE stmt_bultos;

-- 2. Tabla para registrar guías generadas por proveedores (1 pedido puede tener múltiples guías/paquetes)
CREATE TABLE IF NOT EXISTS forwarding_guias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL COMMENT 'FK -> pedidos.id',
    id_provider INT NOT NULL COMMENT 'FK -> forwarding_providers.id',
    numero_orden VARCHAR(100) NOT NULL COMMENT 'Número de orden interna',
    numero_guia VARCHAR(100) NOT NULL COMMENT 'Número de guía generada por el proveedor',
    codigo_paquete VARCHAR(50) NULL COMMENT 'Código de bulto o sub-guía si aplica',
    recolecta VARCHAR(100) NULL COMMENT 'Identificador de la solicitud o recolección',
    seguimiento_url VARCHAR(500) NULL COMMENT 'URL pública para rastreo',
    entrega_min DATETIME NULL COMMENT 'Fecha/hora estimada mínima de entrega',
    entrega_max DATETIME NULL COMMENT 'Fecha/hora estimada máxima de entrega',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider_guia (id_provider, numero_guia),
    KEY idx_pedido (id_pedido),
    KEY idx_provider (id_provider),
    KEY idx_numero_guia (numero_guia),
    KEY idx_numero_orden (numero_orden),
    CONSTRAINT fk_fwd_guia_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
    CONSTRAINT fk_fwd_guia_provider FOREIGN KEY (id_provider) REFERENCES forwarding_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de guías externas asociadas a pedidos';

-- 3. Tabla para registro estructurado de eventos recibidos por Webhook (agnóstica a proveedores)
CREATE TABLE IF NOT EXISTS forwarding_webhook_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_provider INT NOT NULL COMMENT 'FK -> forwarding_providers.id',
    id_pedido INT NULL COMMENT 'FK -> pedidos.id (resuelto por número de guía)',
    numero_guia VARCHAR(100) NOT NULL COMMENT 'Número de guía informada en el webhook',
    codigo_evento VARCHAR(50) NOT NULL COMMENT 'Código de estado externo (ej. 10, 11, 15, 16)',
    nombre_estatus VARCHAR(150) NOT NULL COMMENT 'Descripción textual del estatus enviada por proveedor',
    fecha_evento DATETIME NOT NULL COMMENT 'Fecha y hora real del evento reportada por el proveedor',
    observaciones TEXT NULL COMMENT 'Comentarios u observaciones del evento',
    razon_codigo VARCHAR(50) NULL COMMENT 'Código de razón secundaria si aplica (ej. 105, 115)',
    razon_descripcion VARCHAR(255) NULL COMMENT 'Descripción de la razón si aplica',
    latitud DECIMAL(10,8) NULL COMMENT 'Latitud GPS del evento',
    longitud DECIMAL(11,8) NULL COMMENT 'Longitud GPS del evento',
    tiene_pod TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si el evento incluyó prueba de entrega (POD)',
    pod_info TEXT NULL COMMENT 'Resumen o metadata del POD (sin almacenar base64 gigante en tabla de eventos)',
    payload_raw MEDIUMTEXT NULL COMMENT 'JSON crudo recibido en el webhook para auditoría técnica',
    procesado TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si generó transición de estado, 0 si fue solo informativo/duplicado',
    id_estado_interno INT NULL COMMENT 'ID de estado interno aplicado en pedidos_historial_estados',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_evento_prov_guia_codigo_fecha (id_provider, numero_guia, codigo_evento, fecha_evento, razon_codigo),
    KEY idx_wh_guia (numero_guia),
    KEY idx_wh_pedido (id_pedido),
    KEY idx_wh_provider (id_provider),
    KEY idx_wh_fecha (fecha_evento),
    CONSTRAINT fk_fwd_wh_provider FOREIGN KEY (id_provider) REFERENCES forwarding_providers(id) ON DELETE CASCADE,
    CONSTRAINT fk_fwd_wh_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial estructurado de eventos recibidos por webhook de proveedores';

-- 4. Tabla de homologación de estados y razones por proveedor
CREATE TABLE IF NOT EXISTS forwarding_status_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_provider INT NULL COMMENT 'FK -> forwarding_providers.id (NULL = aplica a todos con el mismo slug)',
    provider_slug VARCHAR(50) NOT NULL COMMENT 'Slug del proveedor (ej. c807, logispro)',
    codigo_externo VARCHAR(50) NOT NULL COMMENT 'Código de estado recibido (ej. 10, 11, 15, 16)',
    razon_codigo VARCHAR(50) NULL COMMENT 'Código de razón opcional para afinar mapeo (NULL = aplica a cualquier razón)',
    descripcion_externa VARCHAR(150) NULL COMMENT 'Descripción para referencia humana',
    id_estado_interno INT NOT NULL COMMENT 'FK -> estados_pedidos.id',
    es_confirmado TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = confirmado por negocio, 0 = pendiente de confirmación',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_prov_slug_cod_razon (provider_slug, codigo_externo, razon_codigo),
    KEY idx_slug (provider_slug),
    KEY idx_estado_interno (id_estado_interno),
    CONSTRAINT fk_fwd_map_estado FOREIGN KEY (id_estado_interno) REFERENCES estados_pedidos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Reglas de homologación de estados de proveedores externos a RutaEx';

-- 5. Catálogo de departamentos y municipios de C807
CREATE TABLE IF NOT EXISTS c807_departamentos (
    id INT PRIMARY KEY COMMENT 'ID del departamento en C807',
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre del departamento según C807',
    codigo VARCHAR(20) NULL COMMENT 'Código C807 (ej. AHU, SS)',
    id_departamento_interno INT NULL COMMENT 'FK -> departamentos.id de RutaEx',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_c807_dep_interno (id_departamento_interno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de departamentos de C807 y homologación interna';

CREATE TABLE IF NOT EXISTS c807_municipios (
    id INT PRIMARY KEY COMMENT 'ID del municipio en C807',
    c807_departamento_id INT NOT NULL COMMENT 'FK -> c807_departamentos.id',
    nombre VARCHAR(150) NOT NULL COMMENT 'Nombre del municipio según C807',
    id_municipio_interno INT NULL COMMENT 'FK -> municipios.id de RutaEx',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_c807_dep (c807_departamento_id),
    KEY idx_c807_mun_interno (id_municipio_interno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de municipios de C807 y homologación interna';

-- 6. Semilla de homologación inicial para C807 (con es_confirmado)
-- Nota: '15' (Llegó a su destino) se registra con es_confirmado=0 hasta que C807 confirme si es entregado final o hub de destino.
INSERT INTO forwarding_status_mapping 
    (provider_slug, codigo_externo, razon_codigo, descripcion_externa, id_estado_interno, es_confirmado, activo)
VALUES
    ('c807', '10', NULL, 'Ingresado al sistema', 11, 1, 1),              -- Pendiente recolección por mensajería
    ('c807', '11', NULL, 'Asignado en vehículo', 2, 1, 1),              -- En ruta o proceso
    ('c807', '13', NULL, 'Recogido en origen', 12, 1, 1),               -- Recolectado por mensajería
    ('c807', '14', NULL, 'En ruta a destino', 2, 1, 1),                 -- En ruta o proceso
    ('c807', '15', NULL, 'Llegó a su destino', 3, 1, 1),                -- Entregado (Confirmado por C807)
    ('c807', '16', '105', 'Reprogramación', 4, 1, 1),                  -- Reprogramado
    ('c807', '16', '109', 'Cerrado casa/negocio', 5, 1, 1),            -- Domicilio cerrado
    ('c807', '16', '110', 'Devolución', 7, 1, 1),                      -- Devuelto
    ('c807', '16', '111', 'No tiene efectivo', 10, 1, 1),               -- No puede pagar recaudo
    ('c807', '16', '112', 'Cliente rechaza envío', 9, 1, 1),            -- Rechazado
    ('c807', '16', '115', 'Dirección incompleta/no contesta', 8, 1, 1), -- Domicilio no encontrado
    ('c807', '16', '118', 'Zona difícil acceso', 16, 1, 1),             -- Incidencia
    ('c807', '16', '128', 'Zona Roja', 16, 1, 1),                       -- Incidencia
    ('c807', '16', NULL, 'Problemas en la gestión (genérico)', 16, 1, 1) -- Incidencia
ON DUPLICATE KEY UPDATE 
    descripcion_externa = VALUES(descripcion_externa),
    id_estado_interno = VALUES(id_estado_interno);

-- 7. Semilla de campos de API y mapeos para C807 (forwarding_api_fields y forwarding_api_mappings)
SET @c807_id = (SELECT id FROM forwarding_providers WHERE slug = 'c807' LIMIT 1);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'recolecta_fecha', 'Fecha Programada de Recolecta (Y-m-d H:i)', 'string', 1, NULL, 10
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'recolecta_fecha');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, '_now_datetime', 'Sin transformación' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'recolecta_fecha'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'tipo_entrega', 'Tipo de Entrega (NRML / PLUS)', 'string', 1, 'NRML', 20
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'tipo_entrega');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'constante:NRML', 'Sin transformación' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'tipo_entrega'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'recolecta_comentario', 'Comentario / Observaciones de Recolecta', 'string', 0, 'Recolectar en bodega principal', 30
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'recolecta_comentario');

-- recolecta_comentario: sin mapping / omitir por defecto

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'sede', 'Código de Sede Remitente', 'int', 0, '1', 40
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'sede');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'constante:1', NULL FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'sede'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].orden', 'Número de Orden / Pedido', 'string', 1, NULL, 50
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].orden');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'numero_orden', 'Sin transformación' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].orden'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].nombre', 'Nombre Completo del Destinatario', 'string', 1, NULL, 60
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].nombre');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'destinatario', 'Sin transformación' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].nombre'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].direccion', 'Dirección de Entrega', 'string', 1, NULL, 70
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].direccion');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'direccion', 'Sin transformación' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].direccion'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].telefono', 'Teléfono del Destinatario', 'string', 1, NULL, 80
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].telefono');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'telefono', 'Sin transformación' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].telefono'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].correo', 'Correo Electrónico del Destinatario', 'string', 1, NULL, 90
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].correo');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'correo', 'Sin transformación' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].correo'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].tipo_servicio', 'Tipo de Servicio (SER, CCE, SEC)', 'string', 1, 'CCE', 100
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].tipo_servicio');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, '_tipo_servicio', 'SER / CCE' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].tipo_servicio'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].monto_cce', 'Monto Contra Entrega (COD)', 'float', 0, NULL, 110
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].monto_cce');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'precio_total_local', 'Condición: tipo_servicio == CCE' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].monto_cce'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].departamento_id', 'ID Departamento (Catálogo C807)', 'int', 1, '11', 120
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].departamento_id');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'departmentName', 'Homologación C807' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].departamento_id'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].municipio_id', 'ID Municipio (Catálogo C807)', 'int', 1, '194', 130
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].municipio_id');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'municipalitiesName', 'Homologación C807' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].municipio_id'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].referencia', 'Referencia / Entre Calles', 'string', 0, NULL, 140
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].referencia');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, '_referencia_extendida', 'Location + betweenStreets + zona' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].referencia'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key), transform_rule = VALUES(transform_rule);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].indicaciones', 'Indicaciones Especiales de Entrega', 'string', 0, NULL, 150
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].indicaciones');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'comentario', NULL FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].indicaciones'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].liquidacion_documentos', 'Liquidación de Documentos', 'boolean', 0, 'false', 160
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].liquidacion_documentos');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'constante:false', NULL FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].liquidacion_documentos'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].seguro', 'Aplica Seguro de Envío', 'boolean', 0, 'false', 170
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].seguro');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'constante:false', NULL FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].seguro'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].detalle[].peso', 'Peso Físico del Paquete', 'float', 1, '1.0', 180
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].detalle[].peso');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'peso', NULL FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].detalle[].peso'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].detalle[].contenido', 'Descripción / Contenido del Paquete', 'string', 1, 'Paquete de mercadería', 190
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].detalle[].contenido');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'productos[].nombre_con_cantidad', NULL FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].detalle[].contenido'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].detalle[].unidad_medida', 'Unidad de Medida del Peso (LB / KG)', 'string', 1, 'LB', 200
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].detalle[].unidad_medida');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'constante:LB', 'Sin transformación' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].detalle[].unidad_medida'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

INSERT INTO forwarding_api_fields 
    (id_provider, field_path, label, field_type, is_required, default_value, sort_order)
SELECT @c807_id, 'guias[].detalle[].codigo', 'Código / SKU del Paquete (Opcional)', 'string', 0, NULL, 210
WHERE @c807_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].detalle[].codigo');

INSERT INTO forwarding_api_mappings (id_api_field, internal_key, transform_rule)
SELECT id, 'productos[].sku', 'Sin transformación' FROM forwarding_api_fields WHERE id_provider = @c807_id AND field_path = 'guias[].detalle[].codigo'
ON DUPLICATE KEY UPDATE internal_key = VALUES(internal_key);

