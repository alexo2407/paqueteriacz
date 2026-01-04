CREATE TABLE IF NOT EXISTS `crm_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `event_type` VARCHAR(50) NOT NULL,
  `related_lead_id` INT NOT NULL,
  `payload` JSON NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_read` (`user_id`, `is_read`),
  INDEX `idx_created` (`created_at`),
  INDEX `idx_lead` (`related_lead_id`),
  INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
