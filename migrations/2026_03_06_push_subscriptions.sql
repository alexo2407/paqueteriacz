-- Migración: Push Subscriptions (Web Push)
-- Fecha: 2026-03-06
-- Descripción: Tabla para almacenar suscripciones push del navegador por usuario.

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `id_usuario`  INT UNSIGNED     NOT NULL COMMENT 'FK lógica a usuarios.id',
  `endpoint`    TEXT             NOT NULL COMMENT 'URL endpoint del push service',
  `p256dh`      VARCHAR(255)     NOT NULL COMMENT 'Clave pública del cliente (base64url)',
  `auth`        VARCHAR(255)     NOT NULL COMMENT 'Auth secret del cliente (base64url)',
  `user_agent`  VARCHAR(255)     DEFAULT NULL COMMENT 'User-Agent del navegador al suscribirse',
  `contexto`    VARCHAR(50)      DEFAULT NULL COMMENT 'logistica|admin|crm|etc',
  `activo`      TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario_activo` (`id_usuario`, `activo`),
  KEY `idx_activo` (`activo`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Suscripciones Web Push por usuario';
