-- Migration: add usuarios_roles pivot table to support many-to-many user roles
-- Safe to run multiple times due to IF NOT EXISTS guards.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS usuarios_roles (
  id_usuario INT NOT NULL,
  id_rol INT NOT NULL,
  PRIMARY KEY (id_usuario, id_rol),
  CONSTRAINT fk_ur_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_ur_rol FOREIGN KEY (id_rol) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill existing single-role assignments into the pivot (first-time run only)
INSERT IGNORE INTO usuarios_roles (id_usuario, id_rol)
SELECT u.id, u.id_rol
FROM usuarios u
WHERE u.id_rol IS NOT NULL;

COMMIT;
