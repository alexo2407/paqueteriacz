-- =====================================================
-- Migración 015: Tablas para Forwarding de Pedidos
-- Sistema de reenvío de pedidos a proveedores externos
-- =====================================================

-- 1. Catálogo de proveedores externos
CREATE TABLE IF NOT EXISTS forwarding_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre visible (ej: LogisPro México)',
    slug VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador de código (ej: logispro)',
    base_url VARCHAR(255) NOT NULL COMMENT 'URL base de la API del proveedor',
    auth_endpoint VARCHAR(255) NOT NULL DEFAULT '/api/AccountApi' COMMENT 'Ruta de autenticación',
    order_endpoint VARCHAR(255) NOT NULL DEFAULT '/api/Orders/OrderAndOrderDetail' COMMENT 'Ruta de creación de orden',
    auth_method ENUM('bearer_jwt', 'api_key', 'basic') NOT NULL DEFAULT 'bearer_jwt' COMMENT 'Método de autenticación',
    credentials TEXT NOT NULL COMMENT 'JSON con credenciales: {"userName":"...","password":"..."}',
    default_config TEXT NULL COMMENT 'JSON con configuración extra del proveedor',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de proveedores externos para forwarding de pedidos';

-- 2. Reglas de forwarding: qué clientes van a qué proveedor
CREATE TABLE IF NOT EXISTS forwarding_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL COMMENT 'FK → clientes.ID_Cliente',
    id_provider INT NOT NULL COMMENT 'FK → forwarding_providers.id',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    config_override TEXT NULL COMMENT 'JSON con configuración específica para este cliente (override)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cliente_provider (id_cliente, id_provider),
    KEY idx_cliente (id_cliente),
    KEY idx_provider (id_provider),
    CONSTRAINT fk_fwdrule_provider FOREIGN KEY (id_provider) REFERENCES forwarding_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Reglas que determinan qué clientes reenvían pedidos a qué proveedor externo';

-- 3. Log de intentos de forwarding
CREATE TABLE IF NOT EXISTS forwarding_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL COMMENT 'FK → pedidos.id',
    id_provider INT NOT NULL COMMENT 'FK → forwarding_providers.id',
    id_rule INT NOT NULL COMMENT 'FK → forwarding_rules.id',
    request_payload TEXT NULL COMMENT 'JSON enviado al proveedor externo',
    response_payload TEXT NULL COMMENT 'JSON recibido del proveedor externo',
    http_status INT NULL COMMENT 'Código HTTP de la respuesta',
    status ENUM('success', 'failed', 'pending') NOT NULL DEFAULT 'pending',
    error_message TEXT NULL COMMENT 'Mensaje de error si falló',
    external_order_id VARCHAR(100) NULL COMMENT 'ID de la orden en el sistema externo',
    attempts INT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pedido (id_pedido),
    KEY idx_provider_log (id_provider),
    KEY idx_status (status),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log de cada intento de forwarding a proveedores externos';
