-- WARNING: Make a full backup before applying. Ensure your app has been updated to use usuarios_roles exclusively.
-- This migration removes the legacy usuarios.id_rol column.

START TRANSACTION;

-- Optional: if you want to preserve a notion of primary role, add a flag column to pivot
-- ALTER TABLE usuarios_roles ADD COLUMN is_principal TINYINT(1) NOT NULL DEFAULT 0;
-- UPDATE usuarios_roles ur
-- INNER JOIN usuarios u ON u.id = ur.id_usuario AND u.id_rol = ur.id_rol
-- SET ur.is_principal = 1;

-- Drop foreign keys or indexes referencing usuarios.id_rol if any (adjust names as needed)
-- Example (uncomment and adapt):
-- ALTER TABLE usuarios DROP FOREIGN KEY fk_usuarios_id_rol;
-- DROP INDEX idx_usuarios_id_rol ON usuarios;

-- Finally, drop the column
ALTER TABLE usuarios DROP COLUMN id_rol;

COMMIT;
