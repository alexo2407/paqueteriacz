# Riesgos de Compatibilidad — Logística Operativa

> Basado en inspección directa del código y la base de datos.
> Prioridad: CRÍTICO > ALTO > MEDIO > BAJO

---

## ✅ MITIGADO en Fase 0.1 — ~~CRÍTICO~~ — IDs de Estado Hardcodeados en PedidoService

**Descripción:** `PedidoService::ESTADO_CANCELADO` usaba el valor `5`, pero en BD el ID 5 es "Domicilio cerrado" y el ID real del estado "Cancelado" es el **17**.

**Impacto anterior:**
- Pedidos con domicilio cerrado liberaban reservas de stock incorrectamente
- Pedidos cancelados (ID 17) no liberaban reservas → fuga permanente de stock

**Corrección aplicada (Fase 0.1 — 2026-07-22):**
- `ESTADO_CANCELADO` corregido de `5` a `17` en `services/PedidoService.php:30`
- El `case self::ESTADO_CANCELADO` en `aplicarStockPorEstado()` ahora apunta al estado correcto
- Prueba de regresión: `test_ESTADO_CANCELADO_apunta_a_cancelado_correcto`

**Riesgo residual pendiente:**
- Registros históricos anteriores a Fase 0.1 pueden tener reservas en estado incorrecto
- Ver `07-auditoria-datos-historicos.sql` para consultas de diagnóstico (solo lectura)
- No se corrigió ningún dato existente

**Archivos modificados:** `services/PedidoService.php:30`

---

## ✅ MITIGADO en Fase 0.1 — ~~CRÍTICO~~ — EntregaModel::marcarEntregado() asigna estado incorrecto

**Descripción:** `id_estado_entrega = 1` asignaba "Asignado" en lugar de "Entregado con éxito" (ID 3).

**Impacto anterior:** Los reportes de entregas exitosas por `estados_entrega` eran incorrectos.

**Corrección aplicada (Fase 0.1 — 2026-07-22):**
- Agregada constante `EntregaModel::ESTADO_ENTREGADO_EXITOSO = 3` en `modelo/entrega.php`
- Ambos paths de `marcarEntregado()` (UPDATE e INSERT fallback) ahora usan la constante
- Prueba de regresión: `test_EntregaModel_tiene_constante_ESTADO_ENTREGADO_EXITOSO`

**Riesgo residual pendiente:**
- Registros históricos en tabla `entregas` con `id_estado_entrega = 1` y `fecha_entrega IS NOT NULL` pueden corresponder a entregas reales con estado incorrecto
- Ver `07-auditoria-datos-historicos.sql` para consulta de diagnóstico (solo lectura)
- No se corrigió ningún dato existente

**Archivos modificados:** `modelo/entrega.php:5,51,52,60,61`

---

## ALTO — Cambios de Estado sin Pasar por PedidoService

**Descripción:** Múltiples puntos del sistema modifican `pedidos.id_estado` directamente sin invocar `PedidoService::aplicarStockPorEstado()`.

**Archivos con UPDATE directo:**
- `modelo/logistica.php:438` — `cambiarEstadoMasivo()` → UPDATE directo sin stock
- `modelo/pedido.php:2041-2042` — UPDATE directo en función de cambio de estado
- `modelo/pedido.php:3375` — `PedidosModel::cambiarEstado()` → UPDATE sin stock
- `api/mensajeria/cambiar_estado.php` — requiere verificación

**Impacto:** Stock y reservas pueden quedar desincronizados si el estado cambia sin pasar por el servicio central.

---

## ALTO — Consultas con IDs Fijos sin Constantes

**Descripción:** Múltiples consultas SQL usan IDs de estado sin pasar por constantes de `PedidoService`.

| Archivo | Línea | Código crítico |
|---|---|---|
| `modelo/logistica.php` | 159, 233 | `id_estado IN (1, 2, 4)` |
| `modelo/logistica.php` | 301 | `id_estado = 14` |
| `modelo/pedido.php` | 2551 | `p.id_estado = 4` |
| `modelo/pedido.php` | 3420-3423 | `CASE WHEN id_estado = 1/4/5 IN(2,3)` |
| `modelo/pedido.php` | 3465 | `id_estado NOT IN (4, 5)` |
| `vista/stock/inventario_periodo.php` | 214 | `ped.id_estado = 1` |

**Impacto:** Si se renumeran estados o se agregan estados nuevos, estas consultas no se actualizarán automáticamente.

**Nota:** No se van a renumerar estados — restricción del proyecto. Pero si se agregan estados nuevos en rangos intermedios, podrían romper lógicas que asuman una lista fija.

---

## ALTO — Falta Total de Pruebas Automatizadas

**Descripción:** El sistema no tiene ninguna prueba unitaria ni de integración. PHPUnit no está instalado.

**Impacto:** Cualquier modificación al sistema puede romper funcionalidad sin detección inmediata.

**Acción:** Instalar PHPUnit (Fase 0). Crear pruebas de regresión para estados y stock antes de cualquier cambio.

---

## ALTO — `tests/` Ignorado en .gitignore

**Descripción:** La línea `tests/` en `.gitignore` impide que los archivos de prueba sean versionados.

**Impacto:** Las pruebas escritas localmente no llegarán al repositorio remoto. CI/CD sería imposible.

**Acción requerida:** Eliminar o modificar la regla en `.gitignore` para permitir `tests/`. Requiere decisión y commit separado.

---

## ALTO — Conexión Automática a Producción en Pruebas

**Descripción:** `modelo/conexion.php` usa las constantes `DB_SCHEMA`, `DB_USER`, `DB_PASSWORD` de `config/config.php`. Si el bootstrap de pruebas no protege correctamente, las pruebas podrían conectarse a `paquetes_apppack` (producción).

**Mitigación implementada en Fase 0:** `tests/bootstrap.php` valida que `DB_SCHEMA` termine en `_test` antes de permitir conexión.

**Acción pendiente:** Crear base de datos `paquetes_apppack_test` o configurar override de constantes para ambiente de pruebas.

---

## MEDIO — Migraciones No Automatizadas

**Descripción:** Las migraciones son scripts SQL manuales en `database/migrations/`. No existe un sistema de tracking de versión aplicada.

**Impacto:** No hay garantía de que todas las migraciones estén aplicadas en todos los ambientes.

**Evidencia:** Existen archivos numerados con gaps (`001`, `002a`, `002b`, `003`...) y scripts sin numeración (`add_crm_indexes.sql`, `create_crm_roles.sql`).

**Acción recomendada:** Implementar una tabla `schema_migrations` con registro de qué scripts ya se aplicaron.

---

## MEDIO — Scripts de Migración Ignorados por .gitignore

**Descripción:** `.gitignore` incluye `run_migration.sh`, lo que significa que el script de ejecución de migraciones no está en el repositorio remoto.

**Impacto:** Otros desarrolladores o ambientes de despliegue no tienen acceso al script de migración.

---

## MEDIO — crearPedido() sin Transacción Explícita

**Descripción:** `PedidosModel::crearPedido()` no envuelve la creación del pedido y su producto en una única transacción. Si la inserción en `pedidos_productos` falla, el pedido existe sin productos.

**Archivos afectados:** `modelo/pedido.php` (~línea 498)

---

## MEDIO — Diferencia entre Devolución Digital y Física

**Descripción:** El sistema tiene dos momentos de devolución:
- Estado 7 ("Devuelto"): acuse digital, sin movimiento de stock
- Estado 15 ("Devolución → entregado a bodega"): llegada física, entrada a stock

No existe un mecanismo que garantice que todo pedido en estado 7 eventualmente llegue a estado 15.

**Impacto:** Paquetes devueltos digitalmente pero no físicamente nunca reintegran stock.

---

## MEDIO — Reportes Cargan Todos los Estados sin Filtro

**Descripción:** Los reportes hacen `SELECT id, nombre_estado FROM estados_pedidos ORDER BY id` sin filtros. Si se agregan estados nuevos, aparecerán automáticamente en todos los filtros de reportes.

**Impacto:** Los reportes podrían mostrar estados operativos internos que no deben ser visibles para el cliente.

**Acción:** Agregar columna `visible_en_reportes TINYINT(1) DEFAULT 1` a `estados_pedidos` en el futuro (Fase 1 o 2).

---

## MEDIO — Riesgo de Duplicación de Stock

**Descripción:** `PedidoService::aplicarReserva()` usa `INSERT IGNORE`, lo que protege contra duplicaciones. Sin embargo, si el estado 1 se aplica más de una vez sin pasar por `PedidoService`, podría ocurrir una duplicación.

**Archivos de riesgo:** cualquier UPDATE directo a `id_estado = 1`.

---

## BAJO — Posible Duplicación de Escaneos (Fase 2)

**Descripción (futura):** Si el UUID del escaneo no se valida correctamente o si dos dispositivos envían el mismo escaneo simultáneamente, podría haber una condición de carrera.

**Mitigación futura:** `UNIQUE KEY(uuid_escaneo)` en `logistica_escaneos` + transacción en el servicio de escaneo.

---

## BAJO — Cierre de Colecta Dos Veces (Fase 2)

**Descripción (futura):** Si dos operadores intentan cerrar la misma colecta simultáneamente, podría ejecutarse doble el proceso de conciliación.

**Mitigación futura:** `UPDATE logistica_colectas SET estado = 'cerrada' WHERE id = ? AND estado = 'abierta'` dentro de transacción con `SELECT FOR UPDATE`.

---

## BAJO — Ruta Sellada Editable (Fase 5)

**Descripción (futura):** Una ruta sellada no debe poder modificarse. Si no existe un check en el controlador, un operador podría agregar pedidos después del sellado.

**Mitigación futura:** Validación de estado en `LogisticaRutaService` antes de cualquier modificación.

---

## BAJO — Notificaciones de Nuevos Estados

**Descripción:** `utils/LogisticaNotifHelper.php` usa el nombre del estado para generar notificaciones. Si se agrega un estado nuevo sin actualizar el helper, la notificación podría mostrar texto genérico o nulo.

---

## BAJO — Roles Intercambiados en config.php

**Descripción:** Los IDs 4 y 5 en la tabla `roles` tienen nombres invertidos respecto a su función.

**Impacto:** Cualquier desarrollador nuevo que lea directamente la BD podría confundirse. El código ya compensa la inversión vía constantes.

**Acción:** Documentar claramente. No modificar sin revisión cuidadosa de todos los `if rol === 4/5`.

---

## Resumen de Prioridades

| Prioridad | Riesgo |
|---|---|
| 🔴 CRÍTICO | ESTADO_CANCELADO = 5 (bug de fuga de stock) |
| 🔴 CRÍTICO | EntregaModel::marcarEntregado() asigna estado incorrecto |
| 🟠 ALTO | Cambios de estado sin PedidoService |
| 🟠 ALTO | IDs hardcodeados en consultas SQL |
| 🟠 ALTO | Sin pruebas automatizadas |
| 🟠 ALTO | tests/ ignorado en .gitignore |
| 🟠 ALTO | Riesgo de conexión a producción en tests |
| 🟡 MEDIO | Migraciones no automatizadas |
| 🟡 MEDIO | run_migration.sh ignorado |
| 🟡 MEDIO | crearPedido() sin transacción explícita |
| 🟡 MEDIO | Brecha entre devolución digital y física |
| 🟡 MEDIO | Reportes sin filtro de estados visibles |
| 🟡 MEDIO | Riesgo de duplicación de stock |
| 🟢 BAJO | Duplicación de escaneos (Fase 2) |
| 🟢 BAJO | Cierre doble de colecta (Fase 2) |
| 🟢 BAJO | Ruta sellada editable (Fase 5) |
| 🟢 BAJO | Notificaciones de estados nuevos |
| 🟢 BAJO | Roles intercambiados en BD |
