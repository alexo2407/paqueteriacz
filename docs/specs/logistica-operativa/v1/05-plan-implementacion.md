# Plan de Implementación — Logística Operativa

> Metodología: Spec-Driven Development
> Estrategia: integración modular progresiva, feature flags, modo sombra

---

## Fase 0 — Línea Base *(COMPLETADA)*

**Estado:** Completada — commit `test: establish logistics operations SDD baseline`

- [x] Documentación SDD (specs v1)
- [x] Catálogo real de estados (`estados_pedidos`)
- [x] Identificación de inconsistencias (CANCELADO, EntregaModel)
- [x] Pruebas de regresión iniciales (`PedidoServiceStateTest`)
- [x] Protección del ambiente de pruebas (bootstrap.php)
- [x] Corrección de .gitignore para incluir `tests/`
- [x] Aprobación de hallazgos y autorización para Fase 1

**Entregables:**
- `docs/specs/logistica-operativa/v1/` (7 archivos)
- `phpunit.xml`
- `tests/bootstrap.php`
- `tests/Regression/PedidoServiceStateTest.php`

---

## Fase 1 — Feature Flags *(IMPLEMENTADA EN CÓDIGO — pendiente de integración funcional)*

**Estado:** Implementada — pendiente de autorización para Fase 2

**Objetivo:** Introducir el mecanismo de control de activación antes de crear ninguna tabla.

**Actividades completadas:**
- [x] Inspección de patrones de configuración existentes (define, getenv, .env)
- [x] Flags agregados en `config/config.php` con guards `defined()` y valores seguros
- [x] Creado `services/LogisticaOperativaFlags.php` con lógica de dependencia entre flags
- [x] Creado `tests/LogisticaOperativa/LogisticaOperativaFlagsTest.php` (13 pruebas unitarias)
- [x] Documentación `docs/specs/logistica-operativa/v1/08-feature-flags.md`
- [x] Plan de implementación actualizado (este archivo)

**Archivos creados/modificados:**
- `config/config.php` (modificado — flags añadidos al final)
- `services/LogisticaOperativaFlags.php` (nuevo)
- `tests/LogisticaOperativa/LogisticaOperativaFlagsTest.php` (nuevo)
- `docs/specs/logistica-operativa/v1/08-feature-flags.md` (nuevo)

**Restricciones respetadas:**
- No se crearon tablas
- No se ejecutaron migraciones
- No se modificaron datos
- No se modificaron estados de pedidos
- No se modificó inventario
- No se hizo commit ni push

---

## Fase 2 — Colectas y Escaneos

**Prerequisito:** Aprobación de Fase 1

**Objetivo:** Implementar el primer módulo funcional (LO-COL-001) en modo sombra.

**Migraciones (aditivas e idempotentes):**
```sql
-- 019_create_logistica_colectas.sql
-- 020_create_logistica_colecta_pedidos.sql
-- 021_create_logistica_escaneos.sql
```

**Tablas:**
- `logistica_colectas` (id, id_cliente, fecha, turno, estado, id_operador, abierta_at, cerrada_at)
- `logistica_colecta_pedidos` (id, id_colecta, id_pedido, numero_orden, estado_colecta)
- `logistica_escaneos` (id, id_colecta, uuid_escaneo, numero_orden, id_usuario, escaneado_at)
  - `UNIQUE KEY (uuid_escaneo)` para garantizar idempotencia

**Servicios nuevos:**
- `services/LogisticaOperativa/ColectaService.php`
- `services/LogisticaOperativa/EscaneoService.php`

**Restricciones modo sombra:**
- No llamar a `PedidoService::aplicarStockPorEstado()`
- No llamar a `UPDATE pedidos SET id_estado`
- Solo leer `pedidos` y escribir en tablas `logistica_*`

---

## Fase 3 — Recepción de Bodega

**Prerequisito:** Aprobación de Fase 2 + validación en producción de 2+ semanas

**Objetivo:** Confirmar físicamente la recepción en bodega y cambiar estados de forma controlada.

**Actividades:**
- Cambio masivo controlado de estado 12 → 1 (con autorización)
- Clasificación geográfica por municipio/departamento
- Auditoría de cada cambio masivo
- Activar `LOGISTICA_OPERATIVA_UPDATE_STATES = true` solo en cliente piloto

---

## Fase 4 — Ubicación Física

**Prerequisito:** Aprobación de Fase 3

**Tabla nueva:**
- `logistica_ubicaciones` (id, id_pedido, bodega, zona, pasillo, estante, cajon, nivel, responsable, ubicado_at)

**Objetivo:** Rastrear dónde está físicamente cada paquete dentro de la bodega.

---

## Fase 5 — Rutas

**Prerequisito:** Aprobación de Fase 4

**Tablas nuevas:**
- `logistica_rutas` (id, nombre, id_mensajero, fecha, estado, sellada_at, id_supervisor)
- `logistica_manifiestos` (id, id_ruta, version, generado_at, total_pedidos, total_cod)

**Actividades:**
- Creación y asignación de rutas
- Sellado (ruta sellada no puede modificarse)
- Generación de manifiesto versionado
- Activar `LOGISTICA_OPERATIVA_ROUTES_ENABLED = true`

---

## Fase 6 — Campo

**Prerequisito:** Aprobación de Fase 5

**Objetivo:** Registrar resultados de campo: entregas, incidencias, reprogramaciones, devoluciones.

**Actividades:**
- Integración con `EntregaModel` (corregir bug `id_estado_entrega = 1`)
- Registro de evidencias fotográficas (URL)
- Reprogramación desde campo
- Inicio de logística inversa

---

## Fase 7 — Liquidación

**Prerequisito:** Aprobación de Fase 6

**Tabla nueva:**
- `logistica_liquidaciones` (id, id_ruta, id_operador, total_cod, total_recibido, diferencia, estado, liquidado_at)

**Actividades:**
- Cuadre físico: paquetes entregados vs manifiestos
- Cuadre financiero: COD recibido vs esperado
- Ajustes con auditoría
- Bloqueos de rutas con diferencia
- Activar `LOGISTICA_OPERATIVA_SETTLEMENT_ENABLED = true`

---

## Fase 8 — Custodia Departamental

**Prerequisito:** Aprobación de Fase 7

**Tabla nueva:**
- `logistica_custodias` (id, id_pedido, id_responsable, id_departamento, id_municipio, custodia_desde, accion_pendiente, estado)

**Objetivo:** Rastrear paquetes que permanecen bajo custodia en departamentos fuera de bodega central.

---

## Fase 9 — Logística Inversa

**Prerequisito:** Aprobación de Fase 8

**Objetivo:**
- Retorno físico a cliente con manifiesto de devolución
- Fulfillment de paquetes rechazados
- Reingreso a Kardex con movimiento de tipo 'devolucion'
- Activar `LOGISTICA_OPERATIVA_INVENTORY_ENABLED = true`

---

## Fase 10 — Activación Gradual

**Prerequisito:** Aprobación de Fase 9 + mínimo 1 mes de operación en modo sombra

**Objetivo:** Activar el módulo en producción de forma controlada.

**Estrategia:**
1. Modo sombra paralelo durante 30 días
2. Seleccionar 1 cliente piloto
3. Seleccionar 1 ruta piloto
4. Comparación diaria de resultados: logística vs sistema clásico
5. Activar flags uno por uno:
   - `LOGISTICA_OPERATIVA_ENABLED = true`
   - `LOGISTICA_OPERATIVA_UPDATE_STATES = true`
   - `LOGISTICA_OPERATIVA_SHADOW_MODE = false`
6. Plan de rollback: revertir flags → el sistema clásico retoma control
7. Monitoreo de stock, reservas y estados durante 2 semanas adicionales

---

## Notas sobre Migraciones

- Todas las migraciones serán numeradas con prefijo (`019_`, `020_`, etc.)
- Todas usarán `IF NOT EXISTS` para ser idempotentes
- Se ejecutarán manualmente (no automáticamente) siguiendo el proceso de `database/run_migration.sh`
- Ninguna migración eliminará columnas ni tablas existentes
- Ninguna migración modificará `estados_pedidos`
