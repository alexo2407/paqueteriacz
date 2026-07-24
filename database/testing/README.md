# Base de datos de pruebas — `paquetes_apppack_test`

> ⚠️ **Nunca usar `paquetes_apppack` para ejecutar pruebas.**
> Las pruebas de integración solo pueden correr contra `paquetes_apppack_test`.

---

## Propósito

`paquetes_apppack_test` es la base de datos exclusiva para pruebas de integración
del proyecto. Permite probar colectas, escaneos, estados, inventario y transacciones
sin riesgo de afectar datos reales de `paquetes_apppack`.

---

## 1. Crear la base de datos

Ejecutar **una sola vez** en el entorno de desarrollo local:

```bash
mysql -u root < database/testing/create_test_database.sql
```

O bien desde MySQL Workbench / phpMyAdmin:

```sql
CREATE DATABASE IF NOT EXISTS paquetes_apppack_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

---

## 2. Copiar únicamente la estructura (sin datos)

```bash
mysqldump -u root --no-data paquetes_apppack > /tmp/schema_only.sql
mysql -u root paquetes_apppack_test < /tmp/schema_only.sql
```

Esto replica tablas, índices y claves foráneas **sin ningún registro**.

---

## 3. Cargar catálogos mínimos

Algunas pruebas necesitan filas de referencia en tablas como `estados_pedidos`
o `roles`. Crear un archivo separado con solo los catálogos requeridos:

```bash
# Exportar solo filas de catálogos (sin datos personales)
mysqldump -u root --no-create-info \
  --tables estados_pedidos roles \
  paquetes_apppack > database/testing/seed_catalogos.sql

# Cargar en la base de pruebas
mysql -u root paquetes_apppack_test < database/testing/seed_catalogos.sql
```

> ⚠️ No exportar tablas `usuarios`, `pedidos`, `clientes` ni ninguna con
> información personal o contraseñas.

---

## 4. Verificar que las pruebas apuntan a `_test`

`phpunit.xml` define la constante `DB_SCHEMA = paquetes_apppack_test`.
El bootstrap la valida automáticamente. Para comprobarlo:

```bash
php -r "
define('DB_SCHEMA', 'paquetes_apppack_test');
// simula assertTestDatabase():
echo str_ends_with('paquetes_apppack_test', '_test') ? 'OK' : 'FALLO';
"
```

También se puede verificar al ejecutar cualquier prueba de integración:
si apuntara a producción, `assertTestDatabase()` lanzaría una excepción
con mensaje claro antes de abrir la conexión.

---

## 5. Limpiar datos de prueba

Para limpiar entre ejecuciones sin borrar la estructura:

```sql
-- Ejecutar al inicio de cada suite de integración (dentro del setUp)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE logistica_colectas;
TRUNCATE TABLE logistica_colecta_pedidos;
TRUNCATE TABLE logistica_escaneos;
SET FOREIGN_KEY_CHECKS = 1;
```

O desde la terminal:

```bash
mysql -u root paquetes_apppack_test -e "
  SET FOREIGN_KEY_CHECKS=0;
  TRUNCATE TABLE logistica_colectas;
  SET FOREIGN_KEY_CHECKS=1;
"
```

---

## 6. Advertencias

- **No ejecutar este script en producción.**
- **No usar `paquetes_apppack` como base de pruebas.**
- Las pruebas unitarias (sin BD) no requieren esta base.
- Las pruebas de integración deben llamar `assertTestDatabase()` en `setUp()`.
- `phpunit.xml` ya configura `DB_SCHEMA=paquetes_apppack_test` de forma automática.
