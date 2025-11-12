# Documentación: `js/pedidos-validation.js`

## Resumen
Este script encapsula la validación y la lógica de UX para los formularios de creación y edición de `pedidos` en el frontend.
- Proporciona validación en tiempo real, gestión de errores resumidos y envío por AJAX con `fetch`.
- Conecta campos de precio entre moneda local y USD y ajusta cantidades según el `data-stock` del `option` seleccionado.

## API pública
- `PedidosValidation.initCrear()` — inicializa comportamientos en la página de creación (form id `formCrearPedido`).
- `PedidosValidation.initEditar()` — inicializa comportamientos en la página de edición (form id `formEditarPedido`).

El script se auto-inicializa en `DOMContentLoaded` si detecta los formularios correspondientes.

## Funciones claves
- `showSummaryErrors(errors)` — muestra una caja con errores (`#formErrors`, `#formErrorsList`).
- `setInvalid(el, msg)` / `clearInvalid(el)` — aplica clases Bootstrap para mostrar el estado del campo.
- `validarTelefono(value)`, `validarDecimal(value)` — validadores simples reutilizables.
- `validateFields(fieldDefs)` — valida un conjunto de definiciones `{id, fn, msg}` y retorna errores.
- `attachRealtime(fieldDefs)` — agrega listeners `input/change` para validación en vivo.

### initCrear
- Lógica adicional:
  - Muestra ayuda de stock usando `data-stock` del option seleccionado.
  - Calcula `precio_local` desde `precio_usd` y viceversa usando `data-tasa` del option de `moneda`.
  - Previene que la cantidad exceda el `data-stock` disponible.
  - Envía el formulario por `fetch` con `FormData`, maneja respuesta JSON y muestra notificaciones con SweetAlert si está disponible.

### initEditar
- Comportamiento similar a `initCrear`, con reglas de validación adaptadas a la edición.

## Recomendaciones
- Asegurar que los elementos del DOM referenciados existan con los ids esperados (`producto_id`, `cantidad_producto`, `precio_local`, `precio_usd`, `moneda`, etc.).
- Si se necesita internacionalización, extraer los `defaultMessages` a un archivo JSON o a data attributes.
- Añadir pruebas E2E (puedes usar Cypress o Playwright) para cubrir los flujos de creación/edición.

## Ejemplo de integración
En la plantilla de creación de pedidos incluye:

```html
<form id="formCrearPedido" action="/api/pedidos/crear.php" method="post">
  <!-- campos: numero_orden, destinatario, telefono, producto_id, cantidad_producto, direccion, latitud, longitud, moneda, precio_local, precio_usd, etc. -->
</form>
<script src="/js/pedidos-validation.js"></script>
<script>PedidosValidation.initCrear();</script>
```

