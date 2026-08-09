# Especificación de Entornos de Trabajo — Logística Operativa

## 1. Visión General

Este documento establece la arquitectura y diferenciación de entornos para el módulo de **Logística Operativa** dentro del proyecto `paqueteriacz`.

---

## 2. Definición de Entornos

### A. Producción Real (Servidor Remoto en Vivo)
- **Base de Datos:** Base de datos activa en el servidor de producción remoto.
- **Estado:** **NO MODIFICADA**. El servidor de producción real permanece intacto.
- **Acceso:** Protegido; no se ejecutan pruebas automáticas ni migraciones de desarrollo sobre este servidor.

### B. Staging Local (`local_staging`)
- **Base de Datos:** `paquetes_apppack` (Copia local estática extraída de producción para pruebas controladas e inspección manual).
- **Host Permitido:** Exclusivamente `localhost`, `127.0.0.1` o `::1`. En `Conexion::conectar()`, cualquier intento de conexión remota cuando `APP_ENV=local_staging` es rechazado automáticamente.
- **Modo de Operación:**
  - `LOGISTICA_OPERATIVA_ENABLED = true`
  - `LOGISTICA_OPERATIVA_SHADOW_MODE = true`
  - `LOGISTICA_OPERATIVA_UPDATE_STATES = false` (No modifica `pedidos.id_estado`)
  - `LOGISTICA_OPERATIVA_INVENTORY_ENABLED = false` (No genera movimientos de kardex/inventario)
  - `LOGISTICA_OPERATIVA_ROUTES_ENABLED = false`
  - `LOGISTICA_OPERATIVA_SETTLEMENT_ENABLED = false`
- **Migraciones Aplicadas Localmente:**
  - `019_create_logistica_colectas_escaneos.sql`
  - `020_create_logistica_bodegas_ubicaciones.sql`
  - `021_complete_logistica_bodega_constraints.sql`
- **Identificación Visual:** Insignia visible en la barra de navegación: `STAGING LOCAL — COPIA DE PRODUCCIÓN`.

### C. Pruebas Automáticas (`testing`)
- **Base de Datos Exclusiva:** `paquetes_apppack_test`
- **Ejecución:** PHPUnit vía `composer test`, `composer test:regression` y `composer test:logistica`.
- **Protección de Seguridad:** `assertTestDatabase()` en `tests/bootstrap.php` y `TestDatabaseConnection.php` valida que `DB_SCHEMA` sea siempre una base terminada en `_test` y prohíbe explícitamente `paquetes_apppack` y bases de producción.

---

## 3. Matriz Resumen de Entornos

| Entorno | `APP_ENV` | Base de Datos | Modifica Servidor Real | Pruebas Automáticas |
| :--- | :---: | :---: | :---: | :---: |
| **Producción Real** | `production` | BD Remota Real | Sí (operaciones normales) | NO |
| **Staging Local** | `local_staging` | `paquetes_apppack` (local) | NO | NO |
| **Pruebas Automáticas** | `testing` | `paquetes_apppack_test` | NO | SÍ |

---

## 4. Proceso Pendiente para Despliegue en Producción Real

Cuando se autorice el despliegue al servidor de producción real:
1. Realizar dump completo de respaldo en el servidor remoto.
2. Ejecutar de forma secuencial las migraciones trazables e idempotentes:
   - `019_create_logistica_colectas_escaneos.sql`
   - `020_create_logistica_bodegas_ubicaciones.sql`
   - `021_complete_logistica_bodega_constraints.sql`
3. Desplegar los archivos de código correspondientes a las rutas y controladores del módulo.
4. Mantener la configuración inicial en `LOGISTICA_OPERATIVA_SHADOW_MODE = true` para monitoreo de lecturas y eventos de escaneo sin alterar estados.
