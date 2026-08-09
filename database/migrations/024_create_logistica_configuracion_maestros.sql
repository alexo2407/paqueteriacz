-- =============================================================================
-- Migration 024: Tablas maestras de Configuración para Logística Operativa
-- =============================================================================

-- 1. Zonas de cobertura / reparto
CREATE TABLE IF NOT EXISTS logistica_zonas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    activa TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Municipios asignados a zonas
CREATE TABLE IF NOT EXISTS logistica_zona_municipios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_zona INT NOT NULL,
    id_municipio INT NOT NULL,
    CONSTRAINT fk_lzm_zona FOREIGN KEY (id_zona) REFERENCES logistica_zonas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Info de mensajeros / repartidores (vehículo, placa, licencia)
CREATE TABLE IF NOT EXISTS logistica_repartidores_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL UNIQUE,
    vehiculo_tipo VARCHAR(50) DEFAULT 'MOTOCICLETA',
    vehiculo_placa VARCHAR(20) DEFAULT NULL,
    licencia VARCHAR(50) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lri_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar zonas por defecto si está vacía
INSERT INTO logistica_zonas (id, nombre, descripcion) VALUES
(1, 'Managua Centro', 'Managua zona urbana y comercial'),
(2, 'Managua Norte / Tipitapa', 'Saba Grande, Tipitapa y zona norte'),
(3, 'Occidente (León / Chinandega)', 'Ruta interurbana occidente'),
(4, 'Sur (Masaya / Granada / Rivas)', 'Ruta interurbana sur')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);
