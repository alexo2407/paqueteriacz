-- ================================================================
-- MIGRATION: Audit System for CSV Imports
-- Descripción: Tabla para registrar historial de importaciones
-- Fecha: 2025-12-05
-- ================================================================

CREATE TABLE IF NOT EXISTS importaciones_csv (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_importacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT NOT NULL,
    
    -- Información del archivo
    archivo_nombre VARCHAR(255) NOT NULL,
    archivo_size_bytes INT UNSIGNED,
    tipo_plantilla ENUM('basico', 'avanzado', 'ejemplo', 'custom') DEFAULT 'custom',
    
    -- Estadísticas de procesamiento
    filas_totales INT UNSIGNED DEFAULT 0,
    filas_exitosas INT UNSIGNED DEFAULT 0,  
    filas_error INT UNSIGNED DEFAULT 0,
    filas_advertencias INT UNSIGNED DEFAULT 0,
    
    tiempo_procesamiento_segundos DECIMAL(10,3),
    
    -- Detalles en JSON
    valores_defecto JSON COMMENT 'Valores por defecto usados durante la importación: {estado, proveedor, moneda, vendedor}',
    productos_creados JSON COMMENT 'Lista de nombres de productos creados automáticamente durante la importación',
    errores_detallados JSON COMMENT 'Array de errores con líneas y descripciones',
    
    -- Estado final
    estado ENUM('completado', 'parcial', 'fallido') DEFAULT 'completado',
    archivo_errores VARCHAR(255) COMMENT 'Nombre del archivo CSV con filas erróneas (si existe)',
    
    -- Foreign keys
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    
    -- Indices para búsquedas
    INDEX idx_fecha (fecha_importacion),
    INDEX idx_usuario (id_usuario),
    INDEX idx_estado (estado)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de auditoría para importaciones CSV de pedidos';

-- ================================================================
-- Datos de ejemplo (opcional, comentar si no se desea)
-- ================================================================

-- Ejemplo de importación exitosa
INSERT INTO importaciones_csv (
    id_usuario, archivo_nombre, archivo_size_bytes, tipo_plantilla,
    filas_totales, filas_exitosas, filas_error, filas_advertencias,
    tiempo_procesamiento_segundos, estado,
    valores_defecto, productos_creados, errores_detallados
) VALUES (
    1,  -- Cambiar por ID de usuario válido
    'pedidos_20251205.csv',
    15360,
    'avanzado',
    100,
    100,
    0,
    5,
    2.45,
    'completado',
    '{"estado": 1, "proveedor": 3, "moneda": 2}',
    '[]',
    '[]'
);

-- Ejemplo de importación parcial
INSERT INTO importaciones_csv (
    id_usuario, archivo_nombre, archivo_size_bytes, tipo_plantilla,
    filas_totales, filas_exitosas, filas_error, filas_advertencias,
    tiempo_procesamiento_segundos, estado,
    valores_defecto, productos_creados, errores_detallados, archivo_errores
) VALUES (
    1,
    'pedidos_test.csv',
    8192,
    'basico',
    50,
    45,
    5,
    10,
    1.23,
    'parcial',
    '{"estado": 1}',
    '["Producto Nuevo A", "Producto Nuevo B"]',
    '[
        "Línea 10: latitud fuera de rango válido (-90 a 90): 95.123",
        "Línea 15: numero_orden 1001 ya existe en la base de datos",
        "Línea 23: longitud vacía",
        "Línea 34: id_producto 999 no existe en la base de datos",
        "Línea 42: cantidad debe ser un número entero mayor a 0"
    ]',
    'import_errors_20251205_143022.csv'
);
