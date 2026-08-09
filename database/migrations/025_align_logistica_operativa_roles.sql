-- =============================================================================
-- 025_align_logistica_operativa_roles.sql
--
-- Migración: Normalización de Roles de Logística Operativa
--
-- Reasigna en roles_permisos los permisos de logistica_operativa_* del rol
-- 'Cliente' (ID 4) al rol 'Proveedor' (ID 5), que es el operador logístico real.
--
-- Después de esta migración:
--   - 'Proveedor' (ID 5) → tiene permisos logistica_operativa_*
--   - 'Administrador' (ID 1) → mantiene sus permisos (sin cambios)
--   - 'Cliente' (ID 4) → NO tiene permisos logistica_operativa_*
--
-- SEGURIDAD E IDEMPOTENCIA:
--   - Usa INSERT IGNORE (nunca duplica filas)
--   - Solo toca la tabla roles_permisos para permisos del módulo logistica_operativa
--   - No modifica: roles, usuarios, pedidos, stock, inventario
--   - Compatible con MariaDB 10.4+ / MySQL 5.7+
--   - Seguro para ejecutar múltiples veces
-- =============================================================================

-- ── 1. Asignar permisos logistica_operativa a Proveedor (ID 5) ───────────────
INSERT IGNORE INTO `roles_permisos` (`id_rol`, `id_permiso`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`nombre_rol` = 'Proveedor'
  AND p.`modulo` = 'logistica_operativa'
  AND p.`activo` = 1;

-- ── 2. Revocar permisos logistica_operativa del rol Cliente (ID 4) ────────────
DELETE rp
FROM `roles_permisos` rp
JOIN `roles`    r ON r.`id`   = rp.`id_rol`
JOIN `permisos` p ON p.`id`   = rp.`id_permiso`
WHERE r.`nombre_rol` = 'Cliente'
  AND p.`modulo` = 'logistica_operativa';


-- ── 3. Verificación post-migración ────────────────────────────────────────────
-- Ejecutar para confirmar que la migración fue exitosa (solo lectura).

-- 3a. Confirmar asignaciones de roles para permisos de logistica_operativa
SELECT r.`nombre_rol`, r.`id` AS id_rol, p.`codigo`, p.`nombre`
FROM `roles_permisos` rp
JOIN `roles`    r ON r.`id`   = rp.`id_rol`
JOIN `permisos` p ON p.`id`   = rp.`id_permiso`
WHERE p.`modulo` = 'logistica_operativa'
ORDER BY r.`nombre_rol`, p.`codigo`;

-- =============================================================================
-- ROLLBACK (ejecutar manualmente si se necesita revertir):
--
-- -- Restaurar permisos logistica_operativa al rol Cliente (ID 4):
-- INSERT IGNORE INTO roles_permisos (id_rol, id_permiso)
-- SELECT r.id, p.id
-- FROM roles r
-- CROSS JOIN permisos p
-- WHERE r.nombre_rol = 'Cliente'
--   AND p.modulo = 'logistica_operativa'
--   AND p.activo = 1;
--
-- -- Revocar permisos del rol Proveedor (ID 5):
-- DELETE rp FROM roles_permisos rp
-- JOIN roles r ON r.id = rp.id_rol
-- JOIN permisos p ON p.id = rp.id_permiso
-- WHERE r.nombre_rol = 'Proveedor'
--   AND p.modulo = 'logistica_operativa';
-- =============================================================================
