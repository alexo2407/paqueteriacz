Migración: trasladar datos legacy de `pedidos.producto`/`pedidos.cantidad` a `pedidos_productos`

Resumen

Este conjunto de archivos contiene un script SQL seguro (no destructivo por defecto) que intenta migrar las filas de pedidos que todavía usan las columnas legacy `producto` y `cantidad` hacia la tabla pivot `pedidos_productos`.

Pasos recomendados antes de ejecutar

1) Backup completo de la base de datos (obligatorio):

```bash
mysqldump -u <user> -p --single-transaction --routines --triggers --databases <db_name> > /tmp/backup_before_migrate.sql
```

2) Ejecutar el script en staging o en una copia de la base de datos.

3) Verificar los resultados:

- Revisa cuántos pedidos migra:
  SELECT COUNT(*) FROM pedidos_productos WHERE id_pedido IN (SELECT id FROM pedidos WHERE producto IS NOT NULL AND TRIM(producto) <> '');

- Revisa productos creados automáticamente:
  SELECT * FROM productos WHERE descripcion IS NULL AND precio_usd IS NULL ORDER BY id DESC LIMIT 50;

4) Si todo está correcto, considerar eliminar las columnas legacy `producto` y `cantidad` de `pedidos` (comentar/ejecutar el ALTER en el script una vez validado).

Notas técnicas

- El script crea tablas temporales `tmp_pedidos_legacy` y `tmp_productos_map` para procesar sólo las filas que aún no están representadas en `pedidos_productos`.
- Para nombres de producto que no existan en `productos`, el script crea un registro mínimo (solo `nombre`). Se recomienda completar `descripcion` y `precio_usd` posteriormente.
- Los triggers de `pedidos_productos` (si existen) se ejecutarán cuando se inserten las filas en `pedidos_productos`, y actualizarán la tabla `stock` según la lógica existente.

Rollback / Reversión

- Si necesitas revertir la migración completa, restaura el backup creado en el paso 1.
- Si solo quieres revertir las inserciones en `pedidos_productos` realizadas por el script, puedes inspeccionar `tmp_pedidos_legacy` para conocer los `id_pedido` afectados y ejecutar:

```sql
DELETE FROM pedidos_productos WHERE id_pedido IN (SELECT id_pedido FROM tmp_pedidos_legacy);
```

Contacto

Para dudas o asistencia, contactar al equipo de desarrollo responsable del repo.
