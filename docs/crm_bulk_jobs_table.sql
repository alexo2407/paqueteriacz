-- Tabla para jobs de actualización masiva asíncrona
CREATE TABLE IF NOT EXISTS crm_bulk_jobs (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT NOT NULL,
    lead_ids JSON NOT NULL,
    estado VARCHAR(50) NOT NULL,
    observaciones TEXT,
    status ENUM('queued', 'processing', 'completed', 'failed') DEFAULT 'queued',
    total_leads INT NOT NULL,
    processed_leads INT DEFAULT 0,
    successful_leads INT DEFAULT 0,
    failed_leads INT DEFAULT 0,
    failed_details JSON,
    error_message TEXT,
    started_at DATETIME,
    completed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
