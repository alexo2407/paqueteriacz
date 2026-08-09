-- =============================================================================
-- 022_create_logistica_operativa_permisos.sql
--
-- Migración: Fase 4 — Permisos Formales para el Módulo Logística Operativa
--
-- Crea la infraestructura de permisos granulares y asigna los dos permisos
-- del módulo a los roles autorizados:
--   - logistica_operativa_bodega    → Bodega y Ubicación Física
--   - logistica_operativa_colectas  → Colectas de Logística
--
-- Roles que reciben estos permisos:
--   - Administrador  (nombre_rol = 'Administrador', ID 1)
--   - Operativo LO   (nombre_rol = 'Cliente',       ID 4)
--     NOTA: En BD el nombre es 'Cliente' pero la constante PHP es
--     ROL_NOMBRE_PROVEEDOR. Esta inversión semántica está preservada
--     intencionalmente (ver migración 008). No se modifica.
--
-- SEGURIDAD E IDEMPOTENCIA:
--   - Usa CREATE TABLE IF NOT EXISTS (sin DROP).
--   - Usa INSERT IGNORE (sin UPDATE ni DELETE sobre datos existentes).
--   - Resuelve IDs de roles por nombre_rol, nunca los hardcodea.
--   - Solo toca tablas 'permisos' y 'roles_permisos' (nuevas).
--   - No modifica: roles, usuarios, pedidos, stock, inventario, reservas.
--   - Compatible con MariaDB 10.4+.
--   - Seguro para ejecutar múltiples veces.
--
-- ORDEN DE EJECUCIÓN:
--   1. Ejecutar primero en paquetes_apppack_test
--   2. Solo con aprobación explícita ejecutar en paquetes_apppack
-- =============================================================================


-- ── 1. Tabla permisos ─────────────────────────────────────────────────────────
-- Catálogo centralizado de permisos disponibles en el sistema.
-- El campo `codigo` es la clave de negocio (UNIQUE, buscable por string).
-- El campo `modulo` permite agrupar permisos por área funcional.

CREATE TABLE IF NOT EXISTS `permisos` (
    `id`          INT(11)       NOT NULL AUTO_INCREMENT,
    `codigo`      VARCHAR(100)  NOT NULL COMMENT 'Clave única del permiso, ej: logistica_operativa_bodega',
    `nombre`      VARCHAR(150)  NOT NULL COMMENT 'Nombre legible del permiso',
    `descripcion` TEXT          NULL     COMMENT 'Descripción de qué permite hacer este permiso',
    `modulo`      VARCHAR(60)   NOT NULL COMMENT 'Módulo al que pertenece el permiso, ej: logistica_operativa',
    `activo`      TINYINT(1)    NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo',
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Índice único por codigo: garantiza que cada permiso es irrepetible
    UNIQUE KEY `uk_permisos_codigo` (`codigo`),

    -- Índice por módulo: facilita listar permisos de un módulo
    KEY `idx_permisos_modulo` (`modulo`),

    -- Índice por activo: filtra permisos deshabilitados sin full scan
    KEY `idx_permisos_activo` (`activo`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Catálogo de permisos granulares del sistema.';


-- ── 2. Tabla roles_permisos ───────────────────────────────────────────────────
-- Tabla pivote N:M entre roles y permisos.
-- PK compuesta (id_rol, id_permiso) garantiza que un rol no reciba
-- el mismo permiso dos veces, incluso con múltiples ejecuciones de la migración.

CREATE TABLE IF NOT EXISTS `roles_permisos` (
    `id_rol`      INT(11) NOT NULL COMMENT 'FK → roles.id',
    `id_permiso`  INT(11) NOT NULL COMMENT 'FK → permisos.id',

    PRIMARY KEY (`id_rol`, `id_permiso`),

    -- Índice inverso: facilita "¿qué roles tienen este permiso?"
    KEY `idx_roles_permisos_permiso` (`id_permiso`),

    CONSTRAINT `fk_rp_rol`
        FOREIGN KEY (`id_rol`)
        REFERENCES `roles` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT `fk_rp_permiso`
        FOREIGN KEY (`id_permiso`)
        REFERENCES `permisos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Asignación de permisos a roles (N:M).';


-- ── 3. Insertar permisos de Logística Operativa ───────────────────────────────
-- INSERT IGNORE: si el codigo ya existe (UNIQUE), la fila se omite sin error.
-- Es seguro ejecutar esta migración varias veces.

INSERT IGNORE INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `activo`) VALUES
(
    'logistica_operativa_bodega',
    'Bodega y Ubicación Física',
    'Permite recepcionar, ubicar, reubicar y retirar paquetes físicos en las bodegas del módulo de logística operativa.',
    'logistica_operativa',
    1
),
(
    'logistica_operativa_colectas',
    'Colectas de Logística',
    'Permite abrir, escanear, cerrar y conciliar colectas de paquetes en el módulo de logística operativa.',
    'logistica_operativa',
    1
);


-- ── 4. Asignar permisos a roles ───────────────────────────────────────────────
-- Resuelve IDs de roles por nombre_rol para evitar hardcodear valores
-- que pueden variar entre entornos.
--
-- IMPORTANTE — Inversión semántica preservada (migración 008):
--   En BD:   nombre_rol = 'Cliente'  → ID 4 → operativo logística (gestión completa)
--   En PHP:  constante ROL_NOMBRE_PROVEEDOR = 'Cliente' → mismo rol
--   En BD:   nombre_rol = 'Proveedor'→ ID 5 → portal cliente (sin acceso LO)
--   En PHP:  constante ROL_NOMBRE_CLIENTE   = 'Proveedor' → mismo rol
-- Esta inversión NO se corrige aquí. El sistema PHP funciona correctamente
-- usando las constantes; el SQL usa los nombres literales de BD.

-- ── 4a. Administrador → ambos permisos ───────────────────────────────────────
INSERT IGNORE INTO `roles_permisos` (`id_rol`, `id_permiso`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`nombre_rol` = 'Administrador'
  AND p.`codigo` IN ('logistica_operativa_bodega', 'logistica_operativa_colectas');

-- ── 4b. Operativo Logística (nombre_rol='Cliente', ID 4) → ambos permisos ────
-- NOTA: 'Cliente' en BD equivale a ROL_NOMBRE_PROVEEDOR en PHP.
INSERT IGNORE INTO `roles_permisos` (`id_rol`, `id_permiso`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`nombre_rol` = 'Cliente'
  AND p.`codigo` IN ('logistica_operativa_bodega', 'logistica_operativa_colectas');


-- ── 5. Verificación post-migración ────────────────────────────────────────────
-- Ejecutar estas consultas para confirmar que la migración fue exitosa.
-- No modifican datos; son solo SELECT.

-- 5a. Confirmar permisos creados
SELECT `id`, `codigo`, `nombre`, `modulo`, `activo`
FROM `permisos`
WHERE `modulo` = 'logistica_operativa'
ORDER BY `codigo`;

-- 5b. Confirmar asignaciones a roles
SELECT r.`nombre_rol`, p.`codigo`, p.`nombre`
FROM `roles_permisos` rp
JOIN `roles`    r ON r.`id`   = rp.`id_rol`
JOIN `permisos` p ON p.`id`   = rp.`id_permiso`
WHERE p.`modulo` = 'logistica_operativa'
ORDER BY r.`nombre_rol`, p.`codigo`;


-- =============================================================================
-- ROLLBACK (ejecutar manualmente si se necesita revertir):
--
-- DELETE rp FROM roles_permisos rp
-- JOIN permisos p ON p.id = rp.id_permiso
-- WHERE p.modulo = 'logistica_operativa';
--
-- DELETE FROM permisos WHERE modulo = 'logistica_operativa';
--
-- -- Solo si se desea eliminar las tablas (únicamente si son completamente nuevas):
-- -- DROP TABLE IF EXISTS roles_permisos;
-- -- DROP TABLE IF EXISTS permisos;
-- =============================================================================
