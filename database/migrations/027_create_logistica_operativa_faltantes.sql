-- 027_create_logistica_operativa_faltantes.sql
-- Creación de estructuras para Liquidaciones, Custodias, Devoluciones y Evidencias de Campo

CREATE TABLE IF NOT EXISTS `logistica_liquidaciones` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_ruta` INT(11) NOT NULL,
  `id_operador` INT(11) NOT NULL,
  `total_cod_esperado` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_cod_recibido` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `diferencia` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_entregados` INT(11) NOT NULL DEFAULT 0,
  `total_devueltos` INT(11) NOT NULL DEFAULT 0,
  `total_reprogramados` INT(11) NOT NULL DEFAULT 0,
  `observaciones` TEXT NULL DEFAULT NULL,
  `estado` ENUM('PENDIENTE','LIQUIDADA','CON_OBSERVACIONES') NOT NULL DEFAULT 'PENDIENTE',
  `liquidado_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_liq_ruta` (`id_ruta`),
  KEY `idx_liq_operador` (`id_operador`),
  KEY `idx_liq_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `logistica_custodias` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` INT(11) NOT NULL,
  `id_bodega_origen` INT(11) NULL DEFAULT NULL,
  `id_departamento_destino` INT(11) NULL DEFAULT NULL,
  `id_responsable` INT(11) NOT NULL,
  `estado` ENUM('EN_TRANSITO','RECIBIDO_CUSTODIA','DESPACHADO_LOCAL','DEVUELTO') NOT NULL DEFAULT 'EN_TRANSITO',
  `observaciones` TEXT NULL DEFAULT NULL,
  `recibido_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cust_pedido` (`id_pedido`),
  KEY `idx_cust_estado` (`estado`),
  KEY `idx_cust_depto` (`id_departamento_destino`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `logistica_devoluciones` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `codigo_manifiesto` VARCHAR(50) NOT NULL,
  `id_cliente` INT(11) NOT NULL,
  `id_proveedor` INT(11) NULL DEFAULT NULL,
  `id_operador` INT(11) NOT NULL,
  `total_paquetes` INT(11) NOT NULL DEFAULT 0,
  `estado` ENUM('BORRADOR','PROGRAMADO','ENTREGADO_CLIENTE') NOT NULL DEFAULT 'BORRADOR',
  `fecha_devolucion` DATE NOT NULL,
  `observaciones` TEXT NULL DEFAULT NULL,
  `firma_cliente` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codigo_manifiesto` (`codigo_manifiesto`),
  KEY `idx_dev_cliente` (`id_cliente`),
  KEY `idx_dev_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `logistica_devolucion_pedidos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_devolucion` INT(11) NOT NULL,
  `id_pedido` INT(11) NOT NULL,
  `observaciones` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dev_pedido` (`id_devolucion`, `id_pedido`),
  CONSTRAINT `fk_dev_pedidos_dev` FOREIGN KEY (`id_devolucion`) REFERENCES `logistica_devoluciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modificación de logistica_ruta_pedidos para agregar campos de evidencia
SET @dbname = DATABASE();
SET @tablename = 'logistica_ruta_pedidos';

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = 'firma_cliente'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `logistica_ruta_pedidos` ADD COLUMN `firma_cliente` LONGTEXT NULL DEFAULT NULL, ADD COLUMN `evidencia_foto_url` VARCHAR(500) NULL DEFAULT NULL, ADD COLUMN `latitud` DECIMAL(10,8) NULL DEFAULT NULL, ADD COLUMN `longitud` DECIMAL(11,8) NULL DEFAULT NULL, ADD COLUMN `notas_campo` TEXT NULL DEFAULT NULL;'
));
PREPARE add_campo_cols FROM @preparedStatement;
EXECUTE add_campo_cols;
DEALLOCATE PREPARE add_campo_cols;
