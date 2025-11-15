# Documentación: `modelo/stock.php`

## Resumen
`StockModel` contiene los métodos de acceso a datos (CRUD) para la tabla `stock`. Está diseñado para operaciones sencillas; la gestión de movimientos debe realizarse preferentemente en la base de datos con triggers.

## Métodos públicos

- listar(): array
  - Retorna todos los registros de stock.
  - En caso de error devuelve `[]`.

- obtenerPorId($id): ?array
  - Retorna el registro asociado al id o `null` si no existe.

- crear(array $data): int|false
  - Inserta un nuevo registro. `data` debe contener `id_vendedor`, `producto`, `cantidad`.
  - Devuelve el ID insertado (int) o `false` en caso de error.

- actualizar($id, array $data): bool
  - Actualiza la fila indicada. Devuelve `true` si la ejecución fue correcta.

- eliminar($id): bool
  - Elimina la fila por id.

- ajustarCantidad($id, $diferencia): bool
  - Incrementa/decrementa la columna `cantidad` por la diferencia indicada.

## Notas importantes
- Los métodos usan consultas parametrizadas (PDO) para prevenir SQL injection.
- `registrarSalida()` está intencionalmente deshabilitado y lanza excepción: usar triggers para movimientos en producción.
- Los métodos registran errores en `logs/errors.log` para facilitar debug.

## Buenas prácticas
- Validar existencia de `id_vendedor` en la tabla de usuarios antes de crear/actualizar si es necesario.
- En operaciones compuestas, envolver en transacciones.
