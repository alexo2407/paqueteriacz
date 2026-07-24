# Criterios de Aceptación — Logística Operativa

> Formato: Gherkin
> Módulo: LO-COL-001 — Conciliación de Colecta

---

## Feature: Colecta y Conciliación Física

### Scenario: Colecta completa

```gherkin
Given un cliente tiene 20 pedidos digitales en estado "Pendiente recolección por mensajería" (ID 11)
And se abre una colecta del turno de la mañana para ese cliente
And la colecta congela un conjunto de 20 pedidos esperados
When el operador escanea físicamente los 20 paquetes con UUID únicos
And cierra la colecta
Then la colecta muestra 20 pedidos esperados
And la colecta muestra 20 paquetes escaneados
And la colecta muestra 0 faltantes
And se registra el id_usuario del operador
And se registra la fecha y hora de cierre
And no se modifica inventario durante el modo sombra
And no se modifica pedidos.id_estado durante el modo sombra
```

---

### Scenario: Colecta con faltantes

```gherkin
Given un cliente tiene 23 pedidos digitales en estado "Pendiente recolección" (ID 11)
And se abre una colecta del turno de la mañana
And la colecta congela un conjunto de 23 pedidos esperados
When el operador escanea físicamente 20 paquetes
And cierra la colecta
Then la colecta muestra 23 pedidos esperados
And la colecta muestra 20 paquetes escaneados
And la colecta identifica 3 pedidos faltantes
And se registran los número_orden de los 3 pedidos faltantes
And se registra el id_usuario del operador
And se registra la fecha y hora de cierre
And no se modifica inventario durante el modo sombra
And no se modifica pedidos.id_estado durante el modo sombra
```

---

### Scenario: Escaneo duplicado

```gherkin
Given un paquete ya fue escaneado en una colecta abierta
And el escaneo tiene UUID "uuid-abc-123"
When el dispositivo vuelve a enviar el mismo UUID "uuid-abc-123"
Then el sistema no crea un segundo registro en logistica_escaneos
And el contador de paquetes escaneados no aumenta
And la respuesta HTTP indica éxito con mensaje "Escaneo ya registrado anteriormente"
And el estado de la colecta no cambia
```

---

### Scenario: Intento de escaneo en colecta cerrada

```gherkin
Given una colecta tiene estado "cerrada"
When un dispositivo intenta registrar un escaneo en esa colecta
Then el sistema rechaza el escaneo con error 409
And no se modifica ningún contador de la colecta
And se registra el intento en auditoria_cambios
```

---

### Scenario: Cierre de colecta ya cerrada

```gherkin
Given una colecta tiene estado "cerrada"
When un operador intenta cerrarla nuevamente vía API
Then el sistema rechaza la operación con error 409
And no altera los contadores de la colecta
And no modifica pedidos.id_estado
And registra el intento en auditoria_cambios con accion "intento_cierre_duplicado"
```

---

### Scenario: Protección de inventario en modo sombra

```gherkin
Given el feature flag LOGISTICA_OPERATIVA_SHADOW_MODE está activo
And una colecta se encuentra abierta
When el operador escanea paquetes y cierra la colecta
Then no se insertan movimientos en la tabla stock
And no se modifica cantidad_disponible en inventario
And no se modifica cantidad_reservada en inventario
And no se insertan filas en pedido_reservas_stock
And no se modifica pedidos.id_estado para ningún pedido conciliado
```

---

### Scenario: Apertura de segunda colecta del mismo turno

```gherkin
Given ya existe una colecta abierta del turno "manana" para el cliente 5
And la fecha es 2026-07-22
When se intenta abrir otra colecta del turno "manana" para el cliente 5 en la misma fecha
Then el sistema rechaza la operación
And retorna error indicando colecta duplicada para ese turno y fecha
And no crea una segunda colecta
```

---

### Scenario: Colecta con pedido de otro cliente

```gherkin
Given se escanea un paquete cuyo numero_orden pertenece al cliente 8
And la colecta abierta pertenece al cliente 5
When el sistema valida el escaneo
Then el sistema rechaza el escaneo
And retorna error "El pedido no pertenece a este cliente"
And no registra el escaneo en la colecta
```

---

## Feature: Protección de Regresión del Sistema Existente

### Scenario: Estado CANCELADO no libera stock incorrecto tras corrección

```gherkin
Given se corrige PedidoService::ESTADO_CANCELADO de 5 a 17
When un pedido entra a estado 17 (Cancelado)
Then PedidoService libera la reserva de stock del pedido
And no hay movimientos de stock para pedidos en estado 5 (Domicilio cerrado)
```

> ⚠️ Este escenario no es implementable hasta que se autorice la corrección del bug.

---

### Scenario: EntregaModel asigna estado correcto de entrega

```gherkin
Given un pedido cambia a estado 3 (Entregado)
When EntregaModel::marcarEntregado() es invocado
Then la fila en entregas tiene id_estado_entrega = 3 (Entregado con éxito)
And no tiene id_estado_entrega = 1 (Asignado)
```

> ⚠️ Este escenario no es implementable hasta que se autorice la corrección del bug.
