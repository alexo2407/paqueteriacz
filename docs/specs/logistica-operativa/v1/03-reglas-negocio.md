# Reglas de Negocio — Logística Operativa

> Versión: 1.0 — Borrador
> Módulo inicial: LO-COL-001 — Conciliación de Colecta

---

# LO-COL-001 — Conciliación de Colecta

## Objetivo

Comparar los pedidos digitales esperados contra los paquetes recibidos físicamente durante cada turno de colecta, sin modificar el sistema existente de pedidos, inventario ni estados durante el modo sombra.

---

## Reglas

| # | Regla |
|---|---|
| 1 | Una colecta pertenece a un cliente (`id_cliente`) |
| 2 | Una colecta pertenece a una fecha (`fecha_colecta DATE`) |
| 3 | Una colecta pertenece a un turno (`turno ENUM('manana','tarde')`) |
| 4 | Los turnos iniciales serán exclusivamente "mañana" y "tarde" |
| 5 | Al abrir una colecta se congela el conjunto de pedidos esperados |
| 6 | Los pedidos esperados deben existir previamente en la tabla `pedidos` |
| 7 | Cada paquete físico debe ser escaneado mediante un UUID único |
| 8 | Un escaneo duplicado (mismo UUID) no puede aumentar el contador |
| 9 | El escaneo debe ser idempotente: segunda llamada = misma respuesta, sin efecto |
| 10 | Al cerrar la colecta se comparan esperados contra escaneados |
| 11 | Los pedidos no escaneados se identifican como faltantes |
| 12 | Los pedidos escaneados se identifican como recibidos físicamente |
| 13 | El cierre registra: operador (`id_usuario`), fecha y hora (`cerrada_at DATETIME`) |
| 14 | Una colecta cerrada (`estado = 'cerrada'`) no puede editarse directamente |
| 15 | Una corrección posterior debe generar un nuevo evento de auditoría |
| 16 | El cierre de colecta debe ejecutarse dentro de una transacción PDO |
| 17 | Durante el **modo sombra**: NO se cambia `pedidos.id_estado` |
| 18 | Durante el **modo sombra**: NO se modifica inventario |
| 19 | Durante el **modo sombra**: NO se genera una salida de stock |
| 20 | El módulo debe poder desactivarse completamente mediante feature flag |

---

## Definiciones

| Término | Definición |
|---|---|
| Colecta | Acto de recoger físicamente paquetes de un cliente en un turno |
| Turno | Período del día (mañana / tarde) |
| Pedidos esperados | Conjunto congelado de pedidos digitales al abrir la colecta |
| Paquete escaneado | Paquete físico verificado mediante lectura QR |
| Faltante | Pedido esperado que no fue escaneado al cerrar la colecta |
| Modo sombra | Operación paralela sin efectos en el sistema productivo |
| UUID de escaneo | Identificador único por evento de escaneo para garantizar idempotencia |

---

## Política de Estados

- El módulo reutilizará la tabla `estados_pedidos` existente.
- No se renumerarán los estados existentes.
- En **modo sombra** no se actualizará ningún `pedidos.id_estado`.
- La futura activación producción traducirá resultados operativos a estados existentes cuando exista equivalencia:
  - Colecta cerrada con pedido escaneado → estado 12 ("Recolectado por mensajería")
  - Colecta cerrada con pedido faltante → permanece en estado 11 + flag en tabla especializada
- Los detalles internos de colecta se guardarán en tablas especializadas (Fase 2).
- "Colecta abierta" y "Colecta cerrada" **NO serán estados del pedido**.
- "Escaneado" **NO será un estado del pedido**.
- "Faltante en colecta" debe evaluarse antes de decidir si se agrega como estado; se recomienda tabla especializada.

---

## Dependencias con el Sistema Existente

| Recurso | Rol en LO-COL-001 |
|---|---|
| `pedidos.id_cliente` | Filtra los pedidos esperados por cliente |
| `pedidos.id_estado` | Se lee pero NO se modifica en modo sombra |
| `estados_pedidos` | Catálogo de referencia para mapeo futuro |
| `AuditoriaModel` | Se usará para registrar eventos de apertura y cierre |
| `PedidoService` | NO se invoca en modo sombra |
| `stock` / `inventario` | NO se modifican en modo sombra |
| Feature flags en `config/config.php` | Control de activación del módulo |

---

## Feature Flags Requeridos (Fase 1)

```php
define('LOGISTICA_OPERATIVA_ENABLED',          false);
define('LOGISTICA_OPERATIVA_SHADOW_MODE',       true);
define('LOGISTICA_OPERATIVA_UPDATE_STATES',     false);
define('LOGISTICA_OPERATIVA_INVENTORY_ENABLED', false);
define('LOGISTICA_OPERATIVA_ROUTES_ENABLED',    false);
define('LOGISTICA_OPERATIVA_SETTLEMENT_ENABLED',false);
```

> ⚠️ No agregar todavía a `config/config.php`. Esto se realiza en Fase 1.

---

## Tablas Futuras (No Crear Todavía)

Las siguientes tablas se crearán en Fase 2, cuando se autorice:

| Tabla | Propósito |
|---|---|
| `logistica_colectas` | Cabecera de colecta (cliente, fecha, turno, estado, operador) |
| `logistica_colecta_pedidos` | Pedidos esperados congelados por colecta |
| `logistica_escaneos` | Escaneos QR con UUID para idempotencia |

---

## Restricciones de Datos

- El UUID de escaneo debe ser validado antes de procesarse (formato UUID v4).
- No se admiten escaneos de paquetes que no pertenezcan al cliente de la colecta.
- No se admiten escaneos en colectas cerradas.
- El operador debe estar autenticado y tener rol habilitado para logística.
