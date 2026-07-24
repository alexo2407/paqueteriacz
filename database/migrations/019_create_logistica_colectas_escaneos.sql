-- =============================================================================
-- 019_create_logistica_colectas_escaneos.sql
--
-- Migración: Fase 2 — Logística Operativa
-- Crea las tres tablas base del módulo de colectas y escaneos físicos.
--
-- Tablas creadas:
--   logistica_colectas         — cabecera de una colecta por cliente/fecha/turno
--   logistica_colecta_pedidos  — pedidos esperados/recibidos dentro de una colecta
--   logistica_escaneos         — registro inmutable de cada escaneo QR
--
-- SEGURIDAD:
--   - Idempotente: usa CREATE TABLE IF NOT EXISTS.
--   - Sin DROP, DELETE, UPDATE ni ALTER sobre tablas existentes.
--   - Sin datos de prueba ni registros.
--   - Solo debe ejecutarse en paquetes_apppack_test durante el desarrollo.
--
-- Motor:    InnoDB
-- Charset:  utf8mb4
-- Collate:  utf8mb4_general_ci  (igual que tablas existentes en paquetes_apppack)
-- MariaDB:  10.4+ compatible
--
-- Nota sobre metadata_json:
--   MariaDB 10.4 implementa JSON como alias de LONGTEXT con una restricción
--   CHECK (JSON_VALID(col)) que no siempre se aplica en insert. Se usa LONGTEXT
--   para garantizar compatibilidad total. La validación JSON es responsabilidad
--   del servicio PHP que escribe en esta columna.
-- =============================================================================

-- ── 1. logistica_colectas ─────────────────────────────────────────────────
-- Una colecta representa la visita de un cliente a bodega en una fecha y turno.
-- La combinación (id_cliente, fecha, turno) es única: no puede haber dos
-- colectas abiertas para el mismo cliente en el mismo turno del mismo día.

CREATE TABLE IF NOT EXISTS logistica_colectas (
    id                  INT(11)         NOT NULL AUTO_INCREMENT,
    id_cliente          INT(11)         NOT NULL COMMENT 'FK → usuarios.id (rol cliente/proveedor)',
    fecha               DATE            NOT NULL COMMENT 'Fecha de la colecta (sin hora)',
    turno               ENUM('MANANA','TARDE') NOT NULL DEFAULT 'MANANA',
    estado              ENUM('ABIERTA','CERRADA','CONCILIADA','CANCELADA')
                                        NOT NULL DEFAULT 'ABIERTA',
    cantidad_esperada   INT(11)         NOT NULL DEFAULT 0,
    cantidad_escaneada  INT(11)         NOT NULL DEFAULT 0,
    cantidad_faltante   INT(11)         NOT NULL DEFAULT 0,
    id_abierta_por      INT(11)         NOT NULL COMMENT 'FK → usuarios.id (operador que abrió)',
    id_cerrada_por      INT(11)         NULL     DEFAULT NULL COMMENT 'FK → usuarios.id (operador que cerró)',
    abierta_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cerrada_at          DATETIME        NULL     DEFAULT NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- Unicidad: un cliente no puede tener dos colectas en el mismo día y turno
    UNIQUE KEY uk_colecta_cliente_fecha_turno (id_cliente, fecha, turno),

    KEY idx_colectas_cliente  (id_cliente),
    KEY idx_colectas_fecha    (fecha),
    KEY idx_colectas_turno    (turno),
    KEY idx_colectas_estado   (estado),
    KEY idx_colectas_abierta_por (id_abierta_por),

    CONSTRAINT fk_colectas_cliente
        FOREIGN KEY (id_cliente)     REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_colectas_abierta_por
        FOREIGN KEY (id_abierta_por) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_colectas_cerrada_por
        FOREIGN KEY (id_cerrada_por) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE SET NULL

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='Cabecera de colecta: visita de un cliente por fecha y turno';


-- ── 2. logistica_colecta_pedidos ──────────────────────────────────────────
-- Cada fila es un pedido que se espera (o se recibió/faltó) en una colecta.
-- Un pedido no puede aparecer dos veces en la misma colecta (UNIQUE).

CREATE TABLE IF NOT EXISTS logistica_colecta_pedidos (
    id              INT(11)     NOT NULL AUTO_INCREMENT,
    id_colecta      INT(11)     NOT NULL COMMENT 'FK → logistica_colectas.id',
    id_pedido       INT(11)     NOT NULL COMMENT 'FK → pedidos.id',
    resultado       ENUM('ESPERADO','RECIBIDO','FALTANTE','EXTRA')
                                NOT NULL DEFAULT 'ESPERADO',
    escaneado_at    DATETIME    NULL DEFAULT NULL COMMENT 'Momento del primer escaneo que cambió resultado',
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- Un pedido solo puede aparecer una vez por colecta
    UNIQUE KEY uk_colecta_pedido (id_colecta, id_pedido),

    KEY idx_cp_colecta    (id_colecta),
    KEY idx_cp_pedido     (id_pedido),
    KEY idx_cp_resultado  (resultado),

    CONSTRAINT fk_cp_colecta
        FOREIGN KEY (id_colecta) REFERENCES logistica_colectas (id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_cp_pedido
        FOREIGN KEY (id_pedido)  REFERENCES pedidos (id) ON UPDATE CASCADE ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='Pedidos esperados/recibidos dentro de cada colecta';


-- ── 3. logistica_escaneos ─────────────────────────────────────────────────
-- Registro inmutable de cada evento de escaneo QR. Cada escaneo tiene un
-- UUID único. La combinación (id_colecta, id_pedido, tipo_evento) es única
-- para evitar duplicados del mismo evento en la misma colecta.

CREATE TABLE IF NOT EXISTS logistica_escaneos (
    id              INT(11)     NOT NULL AUTO_INCREMENT,
    uuid            CHAR(36)    NOT NULL COMMENT 'UUID v4 generado por el cliente — garantiza idempotencia',
    id_colecta      INT(11)     NULL DEFAULT NULL COMMENT 'FK → logistica_colectas.id (puede ser nulo si el escaneo es huérfano)',
    id_pedido       INT(11)     NOT NULL COMMENT 'FK → pedidos.id',
    tipo_evento     VARCHAR(40) NOT NULL COMMENT 'Ej: RECEPCION_COLECTA, ENTRADA_BODEGA, SALIDA_RUTA',
    qr_hash         CHAR(64)    NOT NULL COMMENT 'SHA-256 del contenido del QR escaneado',
    id_operador     INT(11)     NOT NULL COMMENT 'FK → usuarios.id',
    dispositivo     VARCHAR(120) NULL DEFAULT NULL COMMENT 'User-agent o identificador del dispositivo',
    escaneado_at    DATETIME    NOT NULL COMMENT 'Timestamp del momento del escaneo (cliente)',
    recibido_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp de recepción en servidor',
    -- LONGTEXT en lugar de JSON por compatibilidad con MariaDB 10.4.
    -- Validar JSON_VALID() en la capa PHP antes de insertar.
    metadata_json   LONGTEXT    NULL DEFAULT NULL COMMENT 'JSON libre con contexto adicional del escaneo',
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- UUID globalmente único — garantiza idempotencia ante reintentos
    UNIQUE KEY uk_escaneos_uuid (uuid),

    -- Evita duplicar el mismo tipo de evento para el mismo pedido en la misma colecta
    UNIQUE KEY uk_escaneos_colecta_pedido_evento (id_colecta, id_pedido, tipo_evento),

    KEY idx_escaneos_pedido     (id_pedido),
    KEY idx_escaneos_colecta    (id_colecta),
    KEY idx_escaneos_operador   (id_operador),
    KEY idx_escaneos_escaneado  (escaneado_at),

    CONSTRAINT fk_escaneos_colecta
        FOREIGN KEY (id_colecta)   REFERENCES logistica_colectas (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_escaneos_pedido
        FOREIGN KEY (id_pedido)    REFERENCES pedidos (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_escaneos_operador
        FOREIGN KEY (id_operador)  REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='Registro inmutable de eventos de escaneo QR por pedido';
