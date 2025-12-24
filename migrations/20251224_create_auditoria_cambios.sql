-- =====================================================
-- MIGRACIÓN: Sistema de Auditoría Centralizado
-- Fecha: 2025-12-24
-- Descripción: Crea tabla para registrar todos los cambios
--              en tablas maestras del sistema
-- =====================================================

-- =====================================================
-- 1. CREAR TABLA DE AUDITORÍA
-- =====================================================

CREATE TABLE IF NOT EXISTS auditoria_cambios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabla VARCHAR(50) NOT NULL COMMENT 'Nombre de la tabla afectada (productos, monedas, etc.)',
    id_registro INT NOT NULL COMMENT 'ID del registro modificado en la tabla origen',
    accion ENUM('crear', 'actualizar', 'eliminar') NOT NULL COMMENT 'Tipo de operación realizada',
    id_usuario INT NULL COMMENT 'Usuario que realizó la acción (NULL si es sistema)',
    datos_anteriores JSON NULL COMMENT 'Estado anterior del registro (para updates/deletes)',
    datos_nuevos JSON NULL COMMENT 'Estado nuevo del registro (para creates/updates)',
    ip_address VARCHAR(45) NULL COMMENT 'Dirección IP del cliente (IPv4 o IPv6)',
    user_agent VARCHAR(500) NULL COMMENT 'User agent del navegador/cliente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora del cambio',
    
    -- Foreign key al usuario (opcional, permite NULL para operaciones de sistema)
    CONSTRAINT fk_auditoria_usuario 
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) 
        ON DELETE SET NULL ON UPDATE CASCADE,
    
    -- Índices para consultas frecuentes
    INDEX idx_auditoria_tabla (tabla),
    INDEX idx_auditoria_registro (tabla, id_registro),
    INDEX idx_auditoria_usuario (id_usuario),
    INDEX idx_auditoria_accion (accion),
    INDEX idx_auditoria_fecha (created_at),
    INDEX idx_auditoria_tabla_fecha (tabla, created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro centralizado de cambios en tablas maestras del sistema';

-- =====================================================
-- 2. VERIFICAR CREACIÓN
-- =====================================================

-- Descomentar para verificar:
-- DESCRIBE auditoria_cambios;
-- SHOW INDEX FROM auditoria_cambios;

-- =====================================================
-- FIN DE LA MIGRACIÓN
-- =====================================================
