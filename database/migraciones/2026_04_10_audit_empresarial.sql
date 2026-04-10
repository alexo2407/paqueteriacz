-- Migración para Auditoría de Grado Empresarial
-- PaqueteriaCZ

-- 1. Actualizar tabla auditoria_cambios
ALTER TABLE auditoria_cambios
ADD COLUMN url_endpoint VARCHAR(500) NULL AFTER datos_nuevos,
ADD COLUMN http_method VARCHAR(10) NULL AFTER url_endpoint,
ADD COLUMN session_id VARCHAR(100) NULL AFTER id_usuario,
ADD COLUMN user_role VARCHAR(100) NULL AFTER session_id,
ADD COLUMN is_proxy TINYINT(1) DEFAULT 0 AFTER pais_origen,
ADD COLUMN device_os VARCHAR(50) NULL AFTER is_proxy,
ADD COLUMN device_browser VARCHAR(50) NULL AFTER device_os;

ALTER TABLE auditoria_cambios MODIFY COLUMN pais_origen VARCHAR(255) NULL;

-- 2. Actualizar tabla historial_accesos
ALTER TABLE historial_accesos
ADD COLUMN session_id VARCHAR(100) NULL AFTER id_usuario,
ADD COLUMN is_proxy TINYINT(1) DEFAULT 0 AFTER pais_origen,
ADD COLUMN device_os VARCHAR(50) NULL AFTER is_proxy,
ADD COLUMN device_browser VARCHAR(50) NULL AFTER device_os;

ALTER TABLE historial_accesos MODIFY COLUMN pais_origen VARCHAR(255) NULL;
