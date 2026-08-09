-- =============================================================================
-- 023_create_logistica_rutas.sql
--
-- Migración: Fase 5 — Módulo de Rutas y Manifiestos de Despacho
--
-- Tablas creadas:
--   logistica_rutas         — Cabecera de una ruta de despacho asignada a un mensajero.
--   logistica_ruta_pedidos  — Detalle de pedidos asignados a cada ruta de despacho.
--
-- Permiso creado:
--   logistica_operativa_rutas → Creación, gestión y sellado de rutas.
-- =============================================================================

CREATE TABLE IF NOT EXISTS logistica_rutas (
    id                  INT(11)         NOT NULL AUTO_INCREMENT,
    codigo              VARCHAR(40)     NOT NULL COMMENT 'Código único de la ruta, ej: RUT-MGA-20260807-01',
    nombre              VARCHAR(120)    NOT NULL COMMENT 'Nombre descriptivo de la ruta o zona',
    fecha               DATE            NOT NULL COMMENT 'Fecha programada de despacho',
    id_repartidor       INT(11)         NOT NULL COMMENT 'FK → usuarios.id (rol repartidor)',
    estado              ENUM('BORRADOR','ASIGNADA','SELLADA','EN_CURSO','COMPLETADA','CANCELADA')
                                        NOT NULL DEFAULT 'ASIGNADA',
    cantidad_pedidos    INT(11)         NOT NULL DEFAULT 0,
    total_cod           DECIMAL(10,2)   NOT NULL DEFAULT 0.00 COMMENT 'Monto total a recaudar en efectivo',
    id_creada_por       INT(11)         NOT NULL COMMENT 'FK → usuarios.id (operador que creó)',
    id_sellada_por      INT(11)         NULL DEFAULT NULL COMMENT 'FK → usuarios.id (operador que selló)',
    sellada_at          DATETIME        NULL DEFAULT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_rutas_codigo (codigo),
    KEY idx_rutas_fecha (fecha),
    KEY idx_rutas_repartidor (id_repartidor),
    KEY idx_rutas_estado (estado),

    CONSTRAINT fk_rutas_repartidor
        FOREIGN KEY (id_repartidor) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rutas_creada_por
        FOREIGN KEY (id_creada_por)  REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_rutas_sellada_por
        FOREIGN KEY (id_sellada_por) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Cabecera de ruta de despacho';


CREATE TABLE IF NOT EXISTS logistica_ruta_pedidos (
    id              INT(11)         NOT NULL AUTO_INCREMENT,
    id_ruta         INT(11)         NOT NULL COMMENT 'FK → logistica_rutas.id',
    id_pedido       INT(11)         NOT NULL COMMENT 'FK → pedidos.id',
    orden_visita    INT(11)         NOT NULL DEFAULT 1 COMMENT 'Secuencia de entrega en la ruta',
    monto_cod       DECIMAL(10,2)   NOT NULL DEFAULT 0.00 COMMENT 'Monto COD específico de este pedido',
    estado_entrega  ENUM('PENDIENTE','ENTREGADO','INCIDENCIA','DEVUELTO')
                                    NOT NULL DEFAULT 'PENDIENTE',
    entregado_at    DATETIME        NULL DEFAULT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uk_ruta_pedido (id_ruta, id_pedido),
    KEY idx_rp_ruta (id_ruta),
    KEY idx_rp_pedido (id_pedido),
    KEY idx_rp_estado (estado_entrega),

    CONSTRAINT fk_rp_ruta
        FOREIGN KEY (id_ruta)   REFERENCES logistica_rutas (id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_rp_pedido
        FOREIGN KEY (id_pedido) REFERENCES pedidos (id)         ON UPDATE CASCADE ON DELETE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Pedidos asignados a cada ruta de despacho';


-- Insertar permiso de rutas
INSERT IGNORE INTO `permisos` (`codigo`, `nombre`, `descripcion`, `modulo`, `activo`) VALUES
(
    'logistica_operativa_rutas',
    'Gestión y Sellado de Rutas',
    'Permite crear, asignar, armar y sellar rutas de despacho de paquetes a mensajeros.',
    'logistica_operativa',
    1
);

-- Asignar permiso a Administrador y Operativo Logística (nombre_rol='Cliente', ID 4)
INSERT IGNORE INTO `roles_permisos` (`id_rol`, `id_permiso`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permisos` p
WHERE r.`nombre_rol` IN ('Administrador', 'Cliente')
  AND p.`codigo` = 'logistica_operativa_rutas';
