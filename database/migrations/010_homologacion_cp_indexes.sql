-- =============================================================================
-- PROYECTO: PaqueteriaCZ - Homologación de Código Postal + Optimización de Índices
-- AUTOR: Senior PHP/MySQL Engineer
-- FECHA: 2026-02-19
-- =============================================================================

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- 1. CREACIÓN DE TABLA DE HOMOLOGACIÓN (Opción A)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `codigos_postales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_pais` INT NOT NULL,
  `codigo_postal` VARCHAR(20) NOT NULL,
  `id_departamento` INT DEFAULT NULL,
  `id_municipio` INT DEFAULT NULL,
  `id_barrio` INT DEFAULT NULL,
  `nombre_localidad` VARCHAR(150) DEFAULT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Integridad referencial
  CONSTRAINT `fk_cp_pais` FOREIGN KEY (`id_pais`) REFERENCES `paises` (`id`),
  CONSTRAINT `fk_cp_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`id`),
  CONSTRAINT `fk_cp_municipio` FOREIGN KEY (`id_municipio`) REFERENCES `municipios` (`id`),
  CONSTRAINT `fk_cp_barrio` FOREIGN KEY (`id_barrio`) REFERENCES `barrios` (`id`),
  
  -- Unicidad por país + código postal
  UNIQUE KEY `uk_pais_cp` (`id_pais`, `codigo_postal`),
  
  -- Índices para búsquedas rápidas
  INDEX `idx_cp_departamento` (`id_departamento`),
  INDEX `idx_cp_municipio` (`id_municipio`),
  INDEX `idx_cp_barrio` (`id_barrio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- 2. MODIFICACIÓN DE LA TABLA PEDIDOS (Integración CP)
-- -----------------------------------------------------------------------------
-- Agregar campo id_codigo_postal
ALTER TABLE `pedidos` 
ADD COLUMN `id_codigo_postal` INT DEFAULT NULL AFTER `codigo_postal`,
ADD CONSTRAINT `fk_pedidos_cp` FOREIGN KEY (`id_codigo_postal`) REFERENCES `codigos_postales`(`id`);

-- -----------------------------------------------------------------------------
-- 3. CORRECCIÓN DE ÍNDICES REDUNDANTES Y REGLA DE NEGOCIO (Número Orden)
-- -----------------------------------------------------------------------------
-- Según feedback: numero_orden debe ser ÚNICO POR CLIENTE (Option B refined)

-- 3.1 Eliminar índices globales redundantes si existen
-- Nota: Usamos IF EXISTS si la versión de MySQL lo permite, o procedemos con cautela
ALTER TABLE `pedidos` 
DROP INDEX IF EXISTS `uk_pedidos_numero_orden`,
DROP INDEX IF EXISTS `idx_numero_orden_unique`,
DROP INDEX IF EXISTS `uk_pedidos_proveedor_numero`;

-- 3.2 Crear el nuevo UNIQUE compuesto por Cliente
-- Esto asegura que un cliente no repita número de orden, pero distintos clientes sí puedan.
ALTER TABLE `pedidos` 
ADD UNIQUE INDEX `uk_pedidos_cliente_numero` (`id_cliente`, `numero_orden`);

-- -----------------------------------------------------------------------------
-- 4. ÍNDICES ADICIONALES PARA PERFORMANCE
-- -----------------------------------------------------------------------------
-- Índice para búsquedas por id_codigo_postal en pedidos
CREATE INDEX `idx_pedidos_id_cp` ON `pedidos` (`id_codigo_postal`);

COMMIT;
