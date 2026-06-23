-- ============================================================
-- Migración 018: Tabla de historial de documentos API generados
-- Almacena cada documento PDF generado por el wizard de documentación
-- ============================================================

CREATE TABLE IF NOT EXISTS `api_doc_historial` (
    `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `titulo`          VARCHAR(255)    NOT NULL COMMENT 'Título del documento generado',
    `empresa_cliente` VARCHAR(255)    NOT NULL COMMENT 'Empresa/cliente destinatario',
    `url_base`        VARCHAR(500)    NOT NULL COMMENT 'URL base del API documentada',
    `secciones`       JSON            NOT NULL COMMENT 'Array de secciones incluidas',
    `config_json`     JSON            NOT NULL COMMENT 'Configuración completa del documento (todos los datos del wizard)',
    `html_generado`   LONGTEXT        NOT NULL COMMENT 'HTML del documento generado (para re-exportar)',
    `id_usuario`      INT             NULL     COMMENT 'ID del admin que generó el documento',
    `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_empresa` (`empresa_cliente`),
    INDEX `idx_usuario` (`id_usuario`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de documentos de API generados por el wizard';
