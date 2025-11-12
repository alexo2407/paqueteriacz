# Documentación: `controlador/stock.php`

## Resumen
`StockController` es el encargado de coordinar operaciones CRUD sobre la entidad `stock`. Valida entradas y delega la persistencia en `modelo/stock.php` (clase `StockModel`).

> Nota: la lógica de movimientos de stock debe residir en la base de datos (triggers). Evitar duplicar lógica en PHP.

---

## Métodos

### listar()
- Propósito: retornar todos los registros de la tabla `stock`.
- Parámetros: ninguno.
- Retorno: `array` con registros; cada registro contiene `id`, `id_vendedor`, `producto`, `cantidad`.
- Ejemplo de uso:

```php
$res = (new StockController())->listar();
```


### ver($id)
- Propósito: retornar un registro por id.
- Parámetros: `$id` (int)
- Retorno: `array|null` (registro o `null`)

### crear(array $data)
- Propósito: crear un nuevo registro luego de validar los datos.
- Parámetros: `array $data` con `id_vendedor`, `producto`, `cantidad`.
- Comportamiento: llama a `validarDatos()` y luego a `StockModel::crear()`.
- Retorno: array estructurado:
  - Validación fallida: `['success'=>false,'message'=>...,'errors'=>[...]]`
  - Inserción fallida: `['success'=>false,'message'=>...]`
  - Éxito: `['success'=>true,'message'=>...,'id'=> <nuevoId>]`

### actualizar($id, array $data)
- Propósito: actualizar un registro existente.
- Parámetros: `$id` (int), `$data` (array)
- Comportamiento: valida y llama a `StockModel::actualizar()`.
- Retorno: array con `success` y `message`.

### eliminar($id)
- Propósito: eliminar un registro.
- Parámetros: `$id` (int)
- Retorno: array con `success` y `message`.

### validarDatos(array $data)
- Propósito: validar `id_vendedor`, `producto`, `cantidad`.
- Retorno: `['success'=>true]` o `['success'=>false,'message'=>...,'errors'=>[]]`.

---

## Recomendaciones
- Homogeneizar las respuestas: algunas funciones devuelven datos crudos (`listar`/`ver`) mientras que otras devuelven wrappers con `success`.
- Añadir comprobaciones de autorización antes de operaciones mutantes (crear/actualizar/eliminar).
- Considerar transacciones cuando las operaciones afecten múltiples tablas o dependan de triggers.
