-- Migración: Centro de Notificaciones Logísticas
-- Fecha: 2026-03-05
-- Descripción: Crea la tabla notificaciones_logistica para manejar alertas
--              sobre pedidos: creación, cambio de estado, asignaciones, etc.

CREATE TABLE IF NOT EXISTS `notificaciones_logistica` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL COMMENT 'Usuario que recibe la notificación',
  `tipo`        VARCHAR(60)  NOT NULL COMMENT 'pedido_creado|estado_cambiado|asignado|devuelto|reprogramado|comentario|incidencia',
  `titulo`      VARCHAR(255) NOT NULL,
  `mensaje`     TEXT         NULL,
  `pedido_id`   INT UNSIGNED NULL COMMENT 'FK lógica a pedidos.id',
  `payload`     JSON         NULL COMMENT 'Datos extra (estado_anterior, estado_nuevo, etc.)',
  `is_read`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read`   (`user_id`, `is_read`),
  KEY `idx_pedido`      (`pedido_id`),
  KEY `idx_created_at`  (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Notificaciones internas del módulo de logística';
