# Modelo de Estados — Logística Operativa

> Catálogo obtenido directamente de la base de datos `paquetes_apppack`, tabla `estados_pedidos`.
> Consulta ejecutada el 2026-07-22.
> **No se realizó ninguna modificación.**

---

## Catálogo Real de `estados_pedidos`

| ID | Nombre real (BD) | Tipo | Uso actual | Efecto en inventario | Archivos dependientes | Reutilizable en Logística Operativa |
|---|---|---|---|---|---|---|
| 1 | En bodega | Operativo | Estado inicial por default; reserva de stock | RESERVA via PedidoService | `PedidoService`, `LogisticaModel`, `producto.php`, `inventario_periodo.php` | ✅ Sí — Recepción física en bodega |
| 2 | En ruta o proceso | Operativo | Despacho al mensajero; salida física | SALIDA + libera reserva | `PedidoService`, `LogisticaModel` (IN 1,2,4) | ✅ Sí — Despacho de ruta |
| 3 | Entregado | Final | Entrega al destinatario | Salida si vino de 1 directo | `PedidoService`, `EntregaModel`, color map ver.php | ✅ Sí — Entrega confirmada |
| 4 | Reprogramado | Incidencia | Nueva fecha de entrega | Ninguno | `PedidoModel::obtenerReprogramados()` (id=4 fijo), `LogisticaModel` (IN 1,2,4), API reprogramar | ✅ Sí — Reprogramación en campo |
| 5 | Domicilio cerrado | Incidencia | Intento de entrega fallido | Ninguno | `modelo/pedido.php` (IDs hardcodeados) | ✅ Sí — Incidencia en entrega |
| 6 | No hay quien reciba en domicilio | Incidencia | Intento fallido | Ninguno | Color map ver.php | ✅ Sí — Incidencia en entrega |
| 7 | Devuelto | Devolución | Acuse de devolución (sin física aún) | Ninguno — la entrada la genera el 15 | `PedidoService::ESTADO_DEVUELTO=7` | ✅ Sí — Inicio de logística inversa |
| 8 | Domicilio no encontrado | Incidencia | Dirección no localizada | Ninguno | Color map ver.php | ✅ Sí — Incidencia en campo |
| 9 | Rechazado | Incidencia | Destinatario rechaza | Ninguno | `PedidoService::ESTADO_RECHAZADO=9` | ✅ Sí — Rechazo en entrega |
| 10 | No puede pagar recaudo | Incidencia | Problema de pago | Ninguno | Color map ver.php | ✅ Sí — Incidencia financiera |
| 11 | Pendiente recolección por mensajería | Operativo | Pedido listo para colectar | Ninguno | Color map ver.php | ✅ Sí — **Clave para LO-COL-001** |
| 12 | Recolectado por mensajería | Operativo | Colectado del cliente | Ninguno | Color map ver.php | ✅ Sí — Conciliación de colecta |
| 13 | Traslado a punto de distribución | Operativo | En tránsito entre bodegas | Ninguno | Color map ver.php | ✅ Sí — Custodia departamental |
| 14 | Entregado → liquidado | Financiero | Cierre contable de entrega | Ninguno | `LogisticaModel` (id_estado=14 fijo) | ✅ Sí — Liquidación de ruta |
| 15 | Devolución → entregado a bodega | Devolución | Llegada física a bodega | ENTRADA devolucion a stock | `PedidoService::ESTADO_DEVUELTO_BODEGA=15` | ✅ Sí — Retorno físico a bodega |
| 16 | Incidencia | Incidencia | Incidencia general | Ninguno | Color map ver.php | ✅ Sí — Incidencia en bodega |
| 17 | Cancelado | Final | Cancelación definitiva | LIBERA RESERVA — corregido Fase 0.1 | `PedidoService::ESTADO_CANCELADO=17` | ✅ Sí — Cancelación |

---

## Inconsistencias Detectadas

### ✅ INCONSISTENCIA CRÍTICA 1 — PedidoService::ESTADO_CANCELADO — CORREGIDA en Fase 0.1

| Campo | Valor |
|---|---|
| Constante en código (antes) | `PedidoService::ESTADO_CANCELADO = 5` |
| Estado asociado al ID 5 | **"Domicilio cerrado"** |
| Constante corregida | `PedidoService::ESTADO_CANCELADO = 17` |
| Estado asociado al ID 17 | **"Cancelado"** |
| Fecha de corrección | 2026-07-22 (Fase 0.1) |
| Riesgo mitigado | Liberación incorrecta de reservas en pedidos con domicilio cerrado |
| Riesgo mitigado | Fuga de stock en pedidos cancelados que nunca libéraban reservas |
| Datos históricos | **No corregidos.** Ver `07-auditoria-datos-historicos.sql` para diagnóstico de solo lectura |

**Comportamiento anterior defectuoso:**
- Estado 5 ("Domicilio cerrado") ejecutaba `liberarReservaPedido()` incorrectamente
- Estado 17 ("Cancelado") no era reconocido → reservas nunca liberadas (fuga de stock)

**Comportamiento correcto desde Fase 0.1:**
- Estado 5 ("Domicilio cerrado") → sin movimiento de stock
- Estado 17 ("Cancelado") → `liberarReservaPedido()` se ejecuta correctamente

**Prueba de regresión:** `tests/Regression/PedidoServiceStateTest::test_ESTADO_CANCELADO_apunta_a_cancelado_correcto`

---

### ✅ INCONSISTENCIA CRÍTICA 2 — EntregaModel::marcarEntregado — CORREGIDA en Fase 0.1

| Campo | Valor |
|---|---|
| Código anterior | `id_estado_entrega = 1` (literal hardcodeado) |
| Estado asociado al ID 1 | **"Asignado"** |
| Código corregido | `id_estado_entrega = self::ESTADO_ENTREGADO_EXITOSO` |
| Constante introducida | `EntregaModel::ESTADO_ENTREGADO_EXITOSO = 3` |
| Estado asociado al ID 3 | **"Entregado con éxito"** |
| Fecha de corrección | 2026-07-22 (Fase 0.1) |
| Datos históricos | **No corregidos.** Ver `07-auditoria-datos-historicos.sql` para diagnóstico de solo lectura |

**Comportamiento anterior defectuoso:**
- Entregas marcadas como realizadas quedaban con `id_estado_entrega = 1` ("Asignado")
- Reportes de entregas exitosas filtrados por `estados_entrega` eran incorrectos

**Comportamiento correcto desde Fase 0.1:**
- `marcarEntregado()` registra `id_estado_entrega = 3` ("Entregado con éxito") en ambos paths (UPDATE y INSERT fallback)
- Usa la constante `ESTADO_ENTREGADO_EXITOSO` en lugar de un literal

**Prueba de regresión:** `tests/Regression/PedidoServiceStateTest::test_EntregaModel_tiene_constante_ESTADO_ENTREGADO_EXITOSO`

---

### ⚠️ INCONSISTENCIA 3 — Roles intercambiados en BD

Documentado en `config/config.php`:
> El ID 4 en BD se llama "Cliente" pero tiene permisos de Proveedor.
> El ID 5 en BD se llama "Proveedor" pero tiene permisos de Cliente.

Las constantes PHP ya compensan la inversión. Documentado pero sin impacto en esta fase.

---

### ⚠️ INCONSISTENCIA 4 — ids hardcodeados fuera de PedidoService

Archivos que usan IDs de estado directamente sin pasar por constantes:

| Archivo | Línea | Código |
|---|---|---|
| `modelo/logistica.php:159` | — | `id_estado IN (1, 2, 4)` |
| `modelo/logistica.php:233` | — | `id_estado IN (1, 2, 4)` |
| `modelo/logistica.php:301` | — | `id_estado = 14` |
| `modelo/pedido.php:2551` | — | `p.id_estado = 4` |
| `modelo/pedido.php:3420-3423` | — | `CASE WHEN id_estado = 1/4/5 IN(2,3)` |
| `modelo/pedido.php:3465` | — | `id_estado NOT IN (4, 5)` |
| `vista/stock/inventario_periodo.php:214` | — | `ped.id_estado = 1` |

---

### ⚠️ INCONSISTENCIA 5 — tests/ ignorado en .gitignore

El archivo `.gitignore` contiene `tests/` en la línea 23. Esto significa que **los archivos de prueba nunca se subirán al repositorio** con la configuración actual. Se debe decidir si eliminar esta regla o si usar otra carpeta para las pruebas.

---

## Constantes PHP de PedidoService vs Catálogo BD

| Constante | Valor en código | ID real en BD | Nombre real en BD | ¿Coincide? |
|---|---|---|---|---|
| `ESTADO_EN_BODEGA` | 1 | 1 | En bodega | ✅ Sí |
| `ESTADO_EN_RUTA` | 2 | 2 | En ruta o proceso | ✅ Sí |
| `ESTADO_ENTREGADO` | 3 | 3 | Entregado | ✅ Sí |
| `ESTADO_CANCELADO` | ~~5~~ **17** | 17 | Cancelado | ✅ Corregido Fase 0.1 |
| `ESTADO_DEVUELTO` | 7 | 7 | Devuelto | ✅ Sí |
| `ESTADO_RECHAZADO` | 9 | 9 | Rechazado | ✅ Sí |
| `ESTADO_DEVUELTO_BODEGA` | 15 | 15 | Devolución → entregado a bodega | ✅ Sí (semánticamente) |
| _(sin constante)_ | — | 17 | ~~Cancelado (no representado)~~ **Representado via ESTADO_CANCELADO=17** | ✅ Corregido Fase 0.1 |

---

## Propuesta de Reutilización para Logística Operativa

| Necesidad LO | Estado actual propuesto | ID | Observaciones |
|---|---|---|---|
| Orden recibida digital | "En bodega" | 1 | Estado default al crear pedido |
| En bodega física confirmada | "En bodega" | 1 | Mismo estado — la confirmación física se registra en tabla especializada |
| Pendiente de colecta | "Pendiente recolección por mensajería" | 11 | ✅ Equivalencia directa para LO-COL-001 |
| Colectado | "Recolectado por mensajería" | 12 | ✅ Equivalencia directa |
| En ruta / despacho | "En ruta o proceso" | 2 | ✅ Equivalencia directa |
| Entregado | "Entregado" | 3 | ✅ Equivalencia directa |
| Incidencia en entrega | "Incidencia" / 5/6/8/10 | 16 / varios | Depende del tipo; usar 16 para genérica |
| Devuelto / logística inversa | "Devuelto" | 7 | ✅ Equivalencia directa |
| Devuelto físicamente a bodega | "Devolución → entregado a bodega" | 15 | ✅ Equivalencia directa |
| Liquidado | "Entregado → liquidado" | 14 | ✅ Equivalencia directa |
| Cancelado | "Cancelado" | 17 | ✅ Sí — `PedidoService::ESTADO_CANCELADO=17` desde Fase 0.1 |
| Custodia departamental | "Traslado a punto de distribución" | 13 | Parcialmente equivalente — evaluar |

---

## Posibles Estados Nuevos

### 1. Faltante en colecta

| Pregunta | Respuesta |
|---|---|
| ¿Existe equivalente actual? | No exacto. El más cercano es 11 (Pendiente recolección), pero no implica que el paquete "faltó" en la colecta |
| ¿Debe mostrarse en tracking? | Sí — el cliente necesita saber que su paquete no fue recogido |
| ¿Debe producir movimientos de inventario? | No — no llegó físicamente |
| ¿Debe almacenarse como estado general? | Posiblemente sí — pero podría resolverse con un flag en la tabla de colecta |
| ¿Puede resolverse mediante tabla especializada? | Sí — `logistica_colecta_pedidos.estado_colecta = 'faltante'` |
| **Recomendación:** | Resolver vía tabla especializada en Fase 2. No agregar como estado general en esta fase |

### 2. Incidencia en bodega local

| Pregunta | Respuesta |
|---|---|
| ¿Existe equivalente actual? | Sí — estado 16 "Incidencia" |
| ¿Debe mostrarse en tracking? | Depende de la severidad |
| ¿Debe producir movimientos de inventario? | No necesariamente |
| ¿Puede resolverse mediante tabla especializada? | Sí — detalle de incidencia en tabla de ubicaciones |
| **Recomendación:** | Reutilizar estado 16. El detalle de la incidencia en bodega se registra en tabla especializada |

### 3. Custodia departamental

| Pregunta | Respuesta |
|---|---|
| ¿Existe equivalente actual? | Parcialmente — estado 13 "Traslado a punto de distribución" |
| ¿Debe mostrarse en tracking? | Sí |
| ¿Debe producir movimientos de inventario? | No — es una transferencia interna sin salida definitiva |
| ¿Puede resolverse mediante tabla especializada? | Sí — `logistica_custodias` con responsable, departamento, municipio |
| **Recomendación:** | Evaluar si el estado 13 es suficiente o si se necesita uno nuevo. Decidir en Fase 8. No agregar en esta fase |
