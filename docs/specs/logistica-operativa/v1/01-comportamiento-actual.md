# Comportamiento Actual del Sistema — Logística Operativa

> Este documento registra exclusivamente los flujos que **ya existen** en producción.
> No se inventa ni especula ninguna funcionalidad.
> Basado en inspección directa del código, 2026-07-22.

---

## Creación Manual de Pedido

- **Archivo de entrada:** `vista/modulos/pedidos/` (formularios HTML)
- **Controlador:** `controlador/pedido.php`
- **Modelo:** `modelo/pedido.php` → `PedidosModel::crearPedido()`
- **Servicio:** ninguno en la creación directa
- **Tablas afectadas:** `pedidos`, `pedidos_productos`, `auditoria_cambios`
- **Estado inicial:** 1 (En bodega) — `id_estado` default en DB = 1
- **Estado final:** 1 (En bodega)
- **Efecto en inventario:** Ninguno directo. La reserva se aplica cuando el estado cambia a 1 vía `PedidoService`
- **Auditoría:** `AuditoriaModel::registrar('pedidos', $id, 'crear', ...)`
- **Transacción:** Sí, en `insertarPedidosLote()`. En `crearPedido()` sin transacción explícita
- **Riesgos detectados:** `crearPedido()` no envuelve la inserción en `beginTransaction()` explícita; si falla la inserción del producto en `pedidos_productos`, el pedido queda huérfano

---

## Creación por API

- **Archivo de entrada:** `api/pedidos/crear.php` (y rutas en `api/index.php`)
- **Controlador:** `controlador/pedidos/PedidoApiController.php`
- **Modelo:** `modelo/pedido.php` → `PedidosModel::crearPedido()` o `insertarPedidosLote()`
- **Servicio:** `ForwardingService` (si está habilitado, reenvía al proveedor externo)
- **Tablas afectadas:** `pedidos`, `pedidos_productos`, `auditoria_cambios`, `forwarding_log`
- **Estado inicial:** 1 (En bodega)
- **Estado final:** 1 (En bodega)
- **Efecto en inventario:** Ninguno en el momento de creación
- **Auditoría:** Sí, `AuditoriaModel::registrar()`
- **Transacción:** Sí, en modo lote (`insertarPedidosLote`)
- **Riesgos detectados:** No se valida que `id_estado = 1` al momento de crear vía API; si cambia el default de la BD, la lógica de reserva dependería de un cambio de estado manual

---

## Estado Inicial

- **Mecanismo:** `DEFAULT 1` en la columna `pedidos.id_estado` en la BD
- **Equivalente:** "En bodega" = ID 1
- **Riesgo:** El estado 1 en `PedidoService` dispara reserva de stock. Si un pedido se crea directamente sin pasar por `aplicarStockPorEstado()`, la reserva nunca se crea

---

## Validación de Pedidos Duplicados

- **Modelo:** `PedidosModel::existeNumeroOrden($numeroOrden, $idCliente)`
- **Lógica:** verifica `numero_orden` + `id_cliente` en `pedidos`
- **Riesgos detectados:** La función usa `bindParam` con `PDO::PARAM_INT` sobre `numero_orden`, que en BD es `bigint` pero a veces llega como string; puede generar falsos negativos en búsquedas de tipo

---

## Cambio de Estado

- **Rutas:**
  - Web: `logistica/cambiarEstado/{id}` → `controlador/logistica.php::cambiarEstado()`
  - API: `POST /api/mensajeria/cambiar_estado` → `api/mensajeria/cambiar_estado.php`
  - Batch: `cambiarEstados` → `rutas/web.php:1190`
- **Modelo primario:** `PedidosModel::cambiarEstado($idPedido, $nuevoEstado, $observaciones, $idUsuario)`
- **Servicio de stock:** `PedidoService::aplicarStockPorEstado()` — debe llamarse desde dentro de la transacción
- **Tablas afectadas:** `pedidos`, `stock`, `inventario`, `pedido_reservas_stock`, `auditoria_cambios`, `historial_estados_pedidos` (trigger)
- **Estado inicial:** Cualquier estado actual del pedido
- **Estado final:** El nuevo estado recibido
- **Efecto en inventario:**
  - Estado 1: reserva de stock
  - Estado 2: salida física + libera reserva
  - Estado 3: salida si vino directo de 1 (sin pasar por 2)
  - Estado 15: entrada por devolución
  - Estado 5 (CANCELADO en código, pero "Domicilio cerrado" en BD): libera reserva ⚠️
- **Auditoría:** Sí — vía trigger `historial_estados_pedidos` + `AuditoriaModel`
- **Transacción:** El controlador de logística llama a `cambiarEstado()` del modelo; PedidoService debe recibir una transacción activa
- **Riesgos detectados:**
  - `LogisticaModel::cambiarEstadoMasivo()` ejecuta UPDATE directo sin llamar a `PedidoService`
  - Cambios de estado vía `api/mensajeria/cambiar_estado.php` pueden o no invocar `PedidoService` según la implementación

---

## Reserva de Stock

- **Servicio:** `PedidoService::aplicarReserva()` → disparado por estado 1
- **Tabla:** `pedido_reservas_stock` (INSERT IGNORE — idempotente)
- **Tabla secundaria:** `inventario.cantidad_reservada` (UPDATE)
- **Transacción:** Dentro de la transacción del cambio de estado
- **Riesgo:** Si el pedido se crea con `id_estado = 1` por DEFAULT pero `PedidoService` no se llama, la reserva no existe

---

## Salida Física (Estado 2 — En ruta)

- **Servicio:** `PedidoService::aplicarSalidaFisica()`
- **Tablas:** `stock` (INSERT movimiento tipo 'salida'), `inventario` (decrementa `cantidad_disponible`), `pedido_reservas_stock` (marca `liberada = 1`)
- **Transacción:** Dentro de la transacción del cambio de estado
- **Riesgo:** Si el cambio de estado a 2 se realiza directamente vía SQL sin pasar por PedidoService, no se registra el movimiento

---

## Entrega (Estado 3)

- **Modelo:** `EntregaModel::marcarEntregado($idPedido)` — actualiza tabla `entregas`
- **Servicio stock:** `PedidoService::aplicarSalidaFisicaSiPendiente()` — solo si no hubo salida previa
- **Tablas:** `entregas` (fecha_entrega + `id_estado_entrega = 1`), `stock`, `inventario`
- **Auditoría:** Sí
- **⚠️ INCONSISTENCIA CRÍTICA:** `EntregaModel::marcarEntregado()` asigna `id_estado_entrega = 1`, pero en `estados_entrega` el ID 1 es **"Asignado"**, NO "Entregado con éxito". El estado correcto sería ID 3 = "Entregado con éxito"

---

## Reprogramación (Estado 4)

- **API:** `api/pedidos/reprogramar.php`
- **Condición:** Requiere `fecha_entrega` cuando el estado es 4
- **Tablas:** `pedidos` (id_estado + fecha_entrega), `auditoria_cambios`
- **Efecto en inventario:** Ninguno
- **Riesgo:** Sin transacción explícita verificada

---

## Incidencia (Estado 16)

- **Ruta:** `logistica/cambiarEstado/{id}` con estado 16
- **Tablas:** `pedidos`, `auditoria_cambios`
- **Efecto en inventario:** Ninguno definido en `PedidoService` para estado 16
- **Riesgo:** El estado 16 ("Incidencia") no tiene lógica de stock en `PedidoService`

---

## Rechazo (Estado 9)

- **Servicio:** `PedidoService::ESTADO_RECHAZADO = 9` → sin movimiento de stock
- **Comentario en código:** "El destinatario rechaza pero el producto no necesariamente regresa a bodega en ese momento. Usar 'Devuelto a bodega' (15) cuando llegue físicamente."
- **Riesgo:** No hay un flujo que garantice que después del rechazo llegue el estado 15

---

## Devolución (Estado 7)

- **Servicio:** `PedidoService::ESTADO_DEVUELTO = 7` → sin movimiento de stock
- **Comentario:** Solo acuse; la entrada física la genera el estado 15
- **Tablas:** `pedidos`, `auditoria_cambios`
- **Riesgo:** Brecha posible entre estado 7 y estado 15 sin seguimiento

---

## Devolución Física a Bodega (Estado 15)

- **Servicio:** `PedidoService::ESTADO_DEVUELTO_BODEGA = 15` → `aplicarDevolucion()` → entrada a stock
- **Tablas:** `stock` (INSERT tipo 'devolucion'), `inventario` (incrementa `cantidad_disponible`)
- **Transacción:** Dentro del cambio de estado
- **Nombre real en BD del estado 15:** "Devolución → entregado a bodega" ✅ (equivale funcionalmente)

---

## Cancelación (Estado 17 en BD / Estado 5 en PedidoService)

- **⚠️ INCONSISTENCIA CRÍTICA:** `PedidoService::ESTADO_CANCELADO = 5` pero en BD:
  - ID 5 = "Domicilio cerrado"
  - ID 17 = "Cancelado" (el nombre real del estado)
- **Efecto actual:** Al poner un pedido en estado 5 ("Domicilio cerrado"), `PedidoService` ejecuta `liberarReservaPedido()` — libera la reserva de stock incorrectamente para un pedido que simplemente tiene domicilio cerrado
- **Efecto de cancelación real (ID 17):** `PedidoService` no lo reconoce → la reserva de stock no se libera cuando se cancela verdaderamente un pedido

---

## Liquidación (Estado 14)

- **Nombre en BD:** "Entregado → liquidado"
- **Acceso:** `LogisticaModel` → consulta con `id_estado = 14`
- **Efecto en inventario:** Ninguno definido
- **Tablas:** `pedidos` (columna `fecha_liquidacion`)
- **Vista:** `logistica.php::obtenerHistorialCliente()` filtra por estado 14

---

## Auditoría

- **Clase:** `AuditoriaModel::registrar($tabla, $idRegistro, $accion, $idUsuario, $datosAnteriores, $datosNuevos)`
- **Tabla:** `auditoria_cambios`
- **Campos:** tabla, id_registro, accion (crear/actualizar/eliminar), id_usuario, datos_anteriores (JSON), datos_nuevos (JSON), ip, session_id, user_agent
- **Trigger paralelo:** `historial_estados_pedidos` — registra cambios automáticos de `id_estado`

---

## Reportes

- **Vista estatus:** `vista/modulos/pedidos/informes/estatus.php` — usa LEFT JOIN a `estados_pedidos`
- **Vista semana:** `vista/modulos/pedidos/informes/semana.php`
- **Vista región:** `vista/modulos/pedidos/informes/region.php`
- **Vista producto:** `vista/modulos/pedidos/informes/producto.php`
- **Reporte proveedor:** `vista/modulos/pedidos/reporte_proveedor.php`
- **Riesgo:** Todos cargan el listado completo de `estados_pedidos` con `SELECT * FROM estados_pedidos` sin filtro; agregar estados nuevos los incluirá automáticamente en filtros y reportes

---

## Historial / Seguimiento

- **Vista:** `vista/modulos/seguimiento/ver.php`
- **Modelo:** `LogisticaModel` — consultas con `LEFT JOIN estados_pedidos`
- **Trigger:** `historial_estados_pedidos` guarda cada cambio automáticamente

---

## Notificaciones

- **Servicio:** `PushNotificationService.php`
- **Helper:** `utils/LogisticaNotifHelper.php` → `ACCION_ESTADO_CAMBIADO`
- **Tabla:** `pedidos` (JOIN estados_pedidos para obtener nombre del estado)
- **Riesgo:** Si se agregan estados sin actualizar el mapa de notificaciones, los nuevos estados no generarán notificaciones correctamente
