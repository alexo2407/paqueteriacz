-- =============================================================================
-- 020_create_logistica_bodegas_ubicaciones.sql
--
-- Migración: Fase 3A — Recepción en Bodega y Ubicación Física
-- Crea las cuatro tablas del módulo de bodegas, ubicaciones y recepciones.
--
-- Tablas creadas:
--   logistica_bodegas            — catálogo de bodegas físicas
--   logistica_ubicaciones        — posiciones físicas dentro de cada bodega
--   logistica_recepciones        — registro de recepción física de paquetes
--   logistica_ubicacion_historial — historial de movimientos y ubicaciones
--
-- SEGURIDAD:
--   - Idempotente: usa CREATE TABLE IF NOT EXISTS.
--   - Sin DROP, DELETE, UPDATE ni ALTER sobre tablas existentes.
--   - Sin datos de prueba ni registros.
--   - Solo debe ejecutarse en paquetes_apppack_test durante el desarrollo.
--   - No modifica pedidos.id_estado.
--   - No modifica inventario, stock ni reservas.
--
-- Motor:    InnoDB
-- Charset:  utf8mb4
-- Collate:  utf8mb4_general_ci  (igual que tablas existentes en paquetes_apppack)
-- MariaDB:  10.4+ compatible
--
-- Tipos de referencia inspeccionados (migración 019 como patrón):
--   pedidos.id        → INT(11)
--   usuarios.id       → INT(11)
--   paises.id         → INT(11)
--   departamentos.id  → INT(11)
--   municipios.id     → INT(11)
--   logistica_escaneos.id → INT(11)
-- =============================================================================


-- ── 1. logistica_bodegas ──────────────────────────────────────────────────────
-- Catálogo de bodegas físicas. Cada bodega tiene un código único.
-- Las FKs geográficas son opcionales (nullable) para bodegas sin
-- ubicación geográfica exacta registrada.
-- Solo se crean FKs para paises/departamentos/municipios porque sus tipos
-- son compatibles (INT(11)) y las tablas existen en producción.

CREATE TABLE IF NOT EXISTS logistica_bodegas (
    id                  INT(11)         NOT NULL AUTO_INCREMENT,
    codigo              VARCHAR(30)     NOT NULL    COMMENT 'Código único de la bodega, ej: BOD-CENTRAL-01',
    nombre              VARCHAR(120)    NOT NULL    COMMENT 'Nombre descriptivo de la bodega',
    tipo                ENUM('CENTRAL','DEPARTAMENTAL','LOCAL')
                                        NOT NULL    COMMENT 'Categoría operativa de la bodega',
    id_pais             INT(11)         NULL DEFAULT NULL COMMENT 'FK → paises.id (nullable)',
    id_departamento     INT(11)         NULL DEFAULT NULL COMMENT 'FK → departamentos.id (nullable)',
    id_municipio        INT(11)         NULL DEFAULT NULL COMMENT 'FK → municipios.id (nullable)',
    direccion           VARCHAR(255)    NULL DEFAULT NULL COMMENT 'Dirección física de la bodega',
    activa              TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '1=activa, 0=inactiva',
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- Código único global
    UNIQUE KEY uk_bodegas_codigo (codigo),

    -- Índices geográficos
    KEY idx_bodegas_pais           (id_pais),
    KEY idx_bodegas_departamento   (id_departamento),
    KEY idx_bodegas_municipio      (id_municipio),
    KEY idx_bodegas_tipo           (tipo),
    KEY idx_bodegas_activa         (activa),

    -- FKs geográficas (tipos compatibles INT(11))
    CONSTRAINT fk_bodegas_pais
        FOREIGN KEY (id_pais)           REFERENCES paises       (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_bodegas_departamento
        FOREIGN KEY (id_departamento)   REFERENCES departamentos (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_bodegas_municipio
        FOREIGN KEY (id_municipio)      REFERENCES municipios    (id) ON UPDATE CASCADE ON DELETE SET NULL

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='Catálogo de bodegas físicas del sistema de logística';


-- ── 2. logistica_ubicaciones ──────────────────────────────────────────────────
-- Posiciones físicas dentro de una bodega.
-- El código es único dentro de cada bodega (UNIQUE por bodega + código).
-- Ejemplos: 'A-05', 'ZONA-B/ESTANTE-10/CAJON-A5', 'INCIDENCIA-01'.

CREATE TABLE IF NOT EXISTS logistica_ubicaciones (
    id          INT(11)     NOT NULL AUTO_INCREMENT,
    id_bodega   INT(11)     NOT NULL    COMMENT 'FK → logistica_bodegas.id',
    codigo      VARCHAR(80) NOT NULL    COMMENT 'Código de ubicación único dentro de la bodega',
    zona        VARCHAR(50) NULL DEFAULT NULL COMMENT 'Zona de la bodega (ej: A, B, NORTE)',
    pasillo     VARCHAR(30) NULL DEFAULT NULL COMMENT 'Pasillo (ej: 01, 02)',
    estante     VARCHAR(30) NULL DEFAULT NULL COMMENT 'Estante (ej: E10)',
    cajon       VARCHAR(30) NULL DEFAULT NULL COMMENT 'Cajón dentro del estante (ej: A5)',
    nivel       VARCHAR(20) NULL DEFAULT NULL COMMENT 'Nivel vertical (ej: ALTO, MEDIO, BAJO)',
    tipo        ENUM('GENERAL','INCIDENCIA','DEVOLUCION','CUSTODIA')
                            NOT NULL DEFAULT 'GENERAL' COMMENT 'Propósito de la ubicación',
    capacidad   INT(11)     NULL DEFAULT NULL COMMENT 'Capacidad máxima (unidades); NULL = sin límite',
    activa      TINYINT(1)  NOT NULL DEFAULT 1 COMMENT '1=activa, 0=inactiva',
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- Código único dentro de cada bodega
    UNIQUE KEY uk_ubicaciones_bodega_codigo (id_bodega, codigo),

    KEY idx_ubicaciones_bodega  (id_bodega),
    KEY idx_ubicaciones_tipo    (tipo),
    KEY idx_ubicaciones_activa  (activa),

    CONSTRAINT fk_ubicaciones_bodega
        FOREIGN KEY (id_bodega) REFERENCES logistica_bodegas (id) ON UPDATE CASCADE ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='Posiciones físicas dentro de cada bodega';


-- ── 3. logistica_recepciones ──────────────────────────────────────────────────
-- Registro de recepción física de un paquete en una bodega.
-- No modifica pedidos.id_estado ni inventario; solo registra el evento físico.
-- El UUID garantiza idempotencia ante reintentos del cliente.

CREATE TABLE IF NOT EXISTS logistica_recepciones (
    id              INT(11)     NOT NULL AUTO_INCREMENT,
    uuid            CHAR(36)    NOT NULL    COMMENT 'UUID v4 — idempotencia ante reintentos',
    id_pedido       INT(11)     NOT NULL    COMMENT 'FK → pedidos.id',
    id_bodega       INT(11)     NOT NULL    COMMENT 'FK → logistica_bodegas.id',
    id_ubicacion    INT(11)     NULL DEFAULT NULL COMMENT 'FK nullable → logistica_ubicaciones.id',
    id_escaneo      INT(11)     NULL DEFAULT NULL COMMENT 'FK nullable → logistica_escaneos.id (escaneo de origen)',
    tipo_recepcion  ENUM('COLECTA','RETORNO_RUTA','INCIDENCIA','DEVOLUCION')
                                NOT NULL    COMMENT 'Origen del evento de recepción',
    estado          ENUM('RECIBIDO','UBICADO','RETIRADO','CANCELADO')
                                NOT NULL DEFAULT 'RECIBIDO' COMMENT 'Estado actual de la recepción',
    id_operador     INT(11)     NOT NULL    COMMENT 'FK → usuarios.id (operador responsable)',
    recibido_at     DATETIME    NOT NULL    COMMENT 'Timestamp del momento de recepción física',
    observacion     VARCHAR(500) NULL DEFAULT NULL COMMENT 'Notas opcionales sobre la recepción',
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- UUID globalmente único — idempotencia
    UNIQUE KEY uk_recepciones_uuid (uuid),

    KEY idx_recepciones_pedido      (id_pedido),
    KEY idx_recepciones_bodega      (id_bodega),
    KEY idx_recepciones_ubicacion   (id_ubicacion),
    KEY idx_recepciones_escaneo     (id_escaneo),
    KEY idx_recepciones_operador    (id_operador),
    KEY idx_recepciones_tipo        (tipo_recepcion),
    KEY idx_recepciones_estado      (estado),
    KEY idx_recepciones_recibido    (recibido_at),

    CONSTRAINT fk_recepciones_pedido
        FOREIGN KEY (id_pedido)     REFERENCES pedidos              (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_recepciones_bodega
        FOREIGN KEY (id_bodega)     REFERENCES logistica_bodegas    (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_recepciones_ubicacion
        FOREIGN KEY (id_ubicacion)  REFERENCES logistica_ubicaciones (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_recepciones_escaneo
        FOREIGN KEY (id_escaneo)    REFERENCES logistica_escaneos    (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_recepciones_operador
        FOREIGN KEY (id_operador)   REFERENCES usuarios              (id) ON UPDATE CASCADE ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='Recepción física de paquetes en bodega — no modifica pedidos.id_estado';


-- ── 4. logistica_ubicacion_historial ─────────────────────────────────────────
-- Historial inmutable de movimientos de pedidos entre ubicaciones.
-- Solo una fila puede tener activo=1 por pedido a la vez (regla de negocio
-- a aplicar en la capa de servicio PHP; no se impone con índice UNIQUE aquí
-- para mantener flexibilidad y evitar errores en escenarios de migración).
-- Los movimientos anteriores se marcan activo=0 y se preservan como historial.

CREATE TABLE IF NOT EXISTS logistica_ubicacion_historial (
    id              INT(11)     NOT NULL AUTO_INCREMENT,
    id_pedido       INT(11)     NOT NULL    COMMENT 'FK → pedidos.id',
    id_recepcion    INT(11)     NULL DEFAULT NULL COMMENT 'FK nullable → logistica_recepciones.id',
    id_bodega       INT(11)     NOT NULL    COMMENT 'FK → logistica_bodegas.id',
    id_ubicacion    INT(11)     NOT NULL    COMMENT 'FK → logistica_ubicaciones.id',
    id_operador     INT(11)     NOT NULL    COMMENT 'FK → usuarios.id (operador que realizó el movimiento)',
    tipo_movimiento ENUM('INGRESO','REUBICACION','RETIRO','DEVOLUCION')
                                NOT NULL    COMMENT 'Tipo de movimiento físico',
    motivo          VARCHAR(255) NULL DEFAULT NULL COMMENT 'Motivo opcional del movimiento',
    activo          TINYINT(1)  NOT NULL DEFAULT 1 COMMENT '1=ubicación vigente, 0=histórico',
    ubicado_at      DATETIME    NOT NULL    COMMENT 'Momento en que el pedido llegó a esta ubicación',
    retirado_at     DATETIME    NULL DEFAULT NULL COMMENT 'Momento en que el pedido salió de esta ubicación',
    created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    -- Índice principal para buscar la ubicación activa de un pedido
    KEY idx_historial_pedido_activo     (id_pedido, activo),
    KEY idx_historial_pedido            (id_pedido),
    KEY idx_historial_recepcion         (id_recepcion),
    KEY idx_historial_bodega            (id_bodega),
    KEY idx_historial_ubicacion         (id_ubicacion),
    KEY idx_historial_operador          (id_operador),
    KEY idx_historial_tipo              (tipo_movimiento),
    KEY idx_historial_activo            (activo),

    CONSTRAINT fk_historial_pedido
        FOREIGN KEY (id_pedido)     REFERENCES pedidos                   (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_historial_recepcion
        FOREIGN KEY (id_recepcion)  REFERENCES logistica_recepciones     (id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_historial_bodega
        FOREIGN KEY (id_bodega)     REFERENCES logistica_bodegas         (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_historial_ubicacion
        FOREIGN KEY (id_ubicacion)  REFERENCES logistica_ubicaciones     (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_historial_operador
        FOREIGN KEY (id_operador)   REFERENCES usuarios                  (id) ON UPDATE CASCADE ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci
  COMMENT='Historial inmutable de movimientos y ubicaciones de paquetes';


-- =============================================================================
-- Fin de migración 020
-- Tablas creadas: 4
-- Tablas modificadas: 0
-- Datos insertados: 0
-- Efectos en pedidos.id_estado: NINGUNO
-- Efectos en inventario/stock/reservas: NINGUNO
-- =============================================================================
