-- =====================================================
-- Migración 023: Tabla para Datos de Recolección por Pedido
-- Fecha: 2026-10-04
-- Descripción: Almacena los datos de recolección (origen) propios de cada pedido.
-- Relación 0..1 con la tabla pedidos (id_pedido es UNIQUE).
-- =====================================================

CREATE TABLE IF NOT EXISTS `pedido_recoleccion` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `id_pedido` INT(11) NOT NULL,
    `id_pais` INT(11) NOT NULL,
    `id_departamento` INT(11) NOT NULL,
    `id_municipio` INT(11) NOT NULL,
    `direccion` TEXT NOT NULL,
    `contacto` VARCHAR(150) DEFAULT NULL,
    `telefono` VARCHAR(30) DEFAULT NULL,
    `referencia` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pedido_recoleccion_pedido` (`id_pedido`),
    KEY `idx_recoleccion_pais` (`id_pais`),
    KEY `idx_recoleccion_departamento` (`id_departamento`),
    KEY `idx_recoleccion_municipio` (`id_municipio`),
    CONSTRAINT `fk_recoleccion_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_recoleccion_pais` FOREIGN KEY (`id_pais`) REFERENCES `paises` (`id`),
    CONSTRAINT `fk_recoleccion_departamento` FOREIGN KEY (`id_departamento`) REFERENCES `departamentos` (`id`),
    CONSTRAINT `fk_recoleccion_municipio` FOREIGN KEY (`id_municipio`) REFERENCES `municipios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Datos de recolección (origen) por pedido';
