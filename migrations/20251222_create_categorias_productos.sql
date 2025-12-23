-- Migración: Crear tabla de categorías de productos
-- Fecha: 2025-12-22
-- Descripción: Tabla para organizar productos por categorías jerárquicas

CREATE TABLE IF NOT EXISTS categorias_productos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    padre_id INT NULL COMMENT 'Para categorías anidadas (subcategorías)',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key para categorías padre
    FOREIGN KEY (padre_id) REFERENCES categorias_productos(id) ON DELETE SET NULL,
    
    -- Índices para mejor rendimiento
    INDEX idx_categoria_activa (activo),
    INDEX idx_categoria_padre (padre_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Categorías de productos con soporte para jerarquía';

-- Insertar categorías de ejemplo
INSERT INTO categorias_productos (nombre, descripcion, padre_id, activo) VALUES
('Electrónica', 'Productos electrónicos y tecnología', NULL, TRUE),
('Ropa', 'Prendas de vestir y accesorios', NULL, TRUE),
('Alimentos', 'Productos alimenticios', NULL, TRUE),
('Hogar', 'Artículos para el hogar', NULL, TRUE),
('Otros', 'Productos sin categoría específica', NULL, TRUE);
