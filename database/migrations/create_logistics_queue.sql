-- ============================================================================
-- Migración: Sistema de Cola de Trabajos de Logística
-- Fecha: 2026-01-13
-- Descripción: Tabla para gestionar trabajos asíncronos del módulo de logística
-- ============================================================================

CREATE TABLE IF NOT EXISTS logistics_queue (
    -- Identificación
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    
    -- Tipo de trabajo
    job_type VARCHAR(50) NOT NULL COMMENT 'Tipo: generar_guia, actualizar_tracking, validar_direccion, notificar_estado',
    
    -- Relación con pedido
    pedido_id INT NOT NULL COMMENT 'ID del pedido asociado',
    
    -- Datos del trabajo
    payload JSON COMMENT 'Datos adicionales necesarios para procesar el trabajo',
    
    -- Estado y control de procesamiento
    status ENUM('pending','processing','completed','failed') DEFAULT 'pending' 
        COMMENT 'Estado actual del trabajo',
    
    -- Sistema de reintentos
    attempts INT DEFAULT 0 COMMENT 'Número de intentos realizados',
    max_intentos INT DEFAULT 5 COMMENT 'Máximo de intentos permitidos',
    next_retry_at TIMESTAMP NULL COMMENT 'Fecha/hora del próximo reintento',
    
    -- Tracking de errores
    last_error TEXT COMMENT 'Último mensaje de error capturado',
    
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación del trabajo',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
        COMMENT 'Última actualización',
    processed_at TIMESTAMP NULL COMMENT 'Fecha/hora de procesamiento exitoso',
    
    -- Índices para optimización
    INDEX idx_status (status),
    INDEX idx_job_type (job_type),
    INDEX idx_pedido (pedido_id),
    INDEX idx_retry (status, next_retry_at),
    INDEX idx_created (created_at),
    INDEX idx_composite_processing (status, next_retry_at, attempts),
    
    -- Relación con tabla de pedidos
    CONSTRAINT fk_logistics_queue_pedido 
        FOREIGN KEY (pedido_id) 
        REFERENCES pedidos(id) 
        ON DELETE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cola de trabajos asíncronos para el módulo de logística';

-- ============================================================================
-- Comentarios de diseño:
-- 
-- 1. SKIP LOCKED: Los índices están optimizados para consultas con SKIP LOCKED
--    que permiten múltiples workers procesando concurrentemente sin bloqueos.
--
-- 2. Backoff Exponencial: El sistema calcula next_retry_at con backoff:
--    - Intento 1: 1 minuto
--    - Intento 2: 5 minutos
--    - Intento 3: 15 minutos
--    - Intento 4: 1 hora
--    - Intento 5+: 6 horas
--
-- 3. Payload JSON: Permite flexibilidad para diferentes tipos de trabajos
--    sin necesidad de agregar columnas adicionales.
--
-- 4. ON DELETE CASCADE: Si se elimina un pedido, sus trabajos pendientes
--    también se eliminan automáticamente.
-- ============================================================================
