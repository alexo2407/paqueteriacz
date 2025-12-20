-- Migración: Crear tabla password_resets
-- Fecha: 2025-12-20
-- Descripción: Almacena tokens temporales para recuperación de contraseña

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    
    INDEX idx_email (email),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentarios sobre campos:
-- email: Email del usuario que solicitó recuperación
-- token: Hash SHA-256 único para validación
-- created_at: Timestamp de creación del token
-- expires_at: Timestamp de expiración (1 hora desde creación)
-- used_at: Marca cuándo se utilizó el token (permite un solo uso)
