# Informe de QA Manual Local y Validación de Integridad — Fase 3D

## 1. Visión General

- **Proyecto:** `paqueteriacz` (Logística Operativa — Módulo de Bodegas y Ubicaciones Físicas)
- **Entorno:** `local_staging` (Host: `localhost:80`, Base de Datos: `paquetes_apppack`)
- **Configuración de Feature Flags:**
  - `LOGISTICA_OPERATIVA_ENABLED` = `true`
  - `LOGISTICA_OPERATIVA_SHADOW_MODE` = `true`
  - `LOGISTICA_OPERATIVA_UPDATE_STATES` = `false`
  - `LOGISTICA_OPERATIVA_INVENTORY_ENABLED` = `false`
  - `LOGISTICA_OPERATIVA_ROUTES_ENABLED` = `false`
  - `LOGISTICA_OPERATIVA_SETTLEMENT_ENABLED` = `false`
- **Fecha de Ejecución:** 2026-08-05

---

## 2. Matriz de Pruebas Funcionales

| ID | PRUEBA | RESULTADO | EVIDENCIA | OBSERVACIÓN |
| :--- | :--- | :---: | :--- | :--- |
| **P01** | Preparación y Carga de Entorno | **APROBADA** | HTTP 200 en GET `/login` e insignia `STAGING LOCAL — COPIA DE PRODUCCIÓN` presente | Sin errores PHP ni fallas de red |
| **P02** | Autenticación Real de Administrador | **APROBADA** | HTTP 302/200 POST `/login` | Sesión persistente y redirección al Dashboard |
| **P03** | Acceso por Ruta Directa | **APROBADA** | HTTP 200 en `/logistica-operativa/bodega` | Layout cargado con insignia visible y bodega `MGA-CENTRAL` |
| **P04** | Validación de Catálogos | **APROBADA** | API `/catalogos/bodegas` (1) y `/catalogos/ubicaciones` (5) | `MGA-CENTRAL`, `RECEPCION-01`, `INC-E01-A1`, `INC-E01-A5`, `DEV-E01`, `CUS-AREA-01` sin duplicados |
| **P05** | Consulta de Evidencia (Pedido 7482) | **APROBADA** | API `/buscar?q=8005414` e `/historial` | Estado logístico final `RETIRADO`, 0 ubicaciones activas, 3 eventos en historial, `pedidos.id_estado` = 4 |
| **P06** | Selección de Segundo Pedido | **APROBADA** | Pedido ID 15281 (Tracking 4940661260) | Sin recepción previa, sin historial previo, `id_estado` = 4 (Reprogramado) |
| **P07** | Snapshot Previo | **APROBADA** | Guardado bajo `scratch/snapshot_15281.json` | Hash SHA256, estado 4, conteos de pedidos, usuarios, inventario e historial registrados |
| **P08** | Recepción desde la Interfaz | **APROBADA** | API POST `/recepciones/registrar` (HTTP 201) | Recepción creada en estado `RECIBIDO`; reintento de duplicado manejado de forma idempotente/controlada (HTTP 200) |
| **P09** | Asignación a Incidencia (INC-E01-A1) | **APROBADA** | API POST `/ubicaciones/asignar` (HTTP 200) | Ubicación `INC-E01-A1` (Zona INCIDENCIA, E01, A1) asignada; estado `UBICADO`; `pedidos.id_estado` intacto |
| **P10** | Validaciones Negativas | **APROBADA** | Búsqueda `999999999999` (404), Payload Vacío (400) | Respuestas controladas sin errores SQL/stack traces expuestos |
| **P11** | Consulta de Ubicación Actual | **APROBADA** | API GET `/ubicaciones/actual?id_pedido=15281` | Devuelve `INC-E01-A1` activa con datos de zona y operador |
| **P12** | Reubicación a Cajón (INC-E01-A5) | **APROBADA** | API POST `/ubicaciones/reubicar` (HTTP 200) | Ubicación `INC-E01-A1` inactivada; `INC-E01-A5` establecida como única ubicación activa |
| **P13** | Inspección de Historial Cronológico | **APROBADA** | API GET `/ubicaciones/historial?id_pedido=15281` | 4 eventos registrados en orden cronológico (`INGRESO` -> `REUBICACION`), sin duplicaciones |
| **P14** | Retiro Físico de Bodega | **APROBADA** | API POST `/ubicaciones/retirar` (HTTP 200) | Transición a `RETIRADO`, 0 ubicaciones activas; segundo retiro rechazado limpiamente |
| **P15** | Prueba de Permisos (No Autenticado) | **APROBADA** | GET `/logistica-operativa/bodega` redirige a `/login` (302) | Acceso protegido contra clientes no autenticados |
| **P15b**| Prueba de Permisos (Rol no Admin) | **PENDIENTE** | N/A | **PENDIENTE — REQUIERE USUARIO DE ROL NO ADMINISTRADOR** |
| **P16** | Adaptabilidad Responsive | **APROBADA** | Inspección de layouts en 1440×900, 768×1024 y 390×844 | Sin desbordamientos de tabla ni rotura de header |
| **P17** | Accesibilidad Básica | **APROBADA** | Formulario con etiquetas asociadas, SweetAlert2 modal accesible | Modales cierran con Escape |
| **P18** | Verificación de Integridad Final | **APROBADA** | Comparación contra snapshot previo | Hash de fila del pedido 15281 idéntico, `id_estado` idéntico (4), conteo de usuarios y pedidos idéntico |
| **P19** | Auditoría de Logs | **APROBADA** | Inspección de Apache/PHP y respuestas JSON | 0 excepciones fatales sin manejar, 0 errores 500 |
| **P20** | Pruebas Automáticas de Regresión | **APROBADA** | PHPUnit Suites (`regression`, `logistica-operativa`, completa) | 204 pruebas en 3 suites ejecutadas con exit code 0 |

---

## 3. Resumen Ejecutivo

- **Autenticación:** APROBADA
- **Routing:** APROBADA
- **Catálogos:** APROBADA
- **Recepción:** APROBADA
- **Asignación:** APROBADA
- **Consulta:** APROBADA
- **Reubicación:** APROBADA
- **Historial:** APROBADA
- **Retiro:** APROBADA
- **Validaciones negativas:** APROBADA
- **Permisos:** APROBADA / PENDIENTE (Rol no Administrador)
- **Responsive:** APROBADA
- **Accesibilidad:** APROBADA
- **Integridad:** APROBADA
- **Pruebas automáticas:** APROBADA (204 tests, exit code 0)

---

## 4. Registro de Correcciones Menores Aplicadas

1. **Defecto:** En `rutas/web.php` existía un `require_once __DIR__ . '/logistica_bodega.php';` duplicado en la entrada principal.
   - **Corrección:** Eliminado la inclusión redundante en `web.php` para que la enrutación a `/logistica-operativa/bodega` fluya de forma limpia y única a través de `rutas/logistica_operativa.php`.
2. **Defecto:** En `api/logistica-operativa/bodega/pedidos/buscar.php` la consulta SQL hacía referencia a `p.municipio`, una columna inexistente en la tabla `pedidos`.
   - **Corrección:** Corregida la consulta SQL seleccionando `p.municipalitiesName` y `p.departmentName`.
3. **Defecto:** En `api/logistica-operativa/bodega/auth/session-token.php` la sentencia `use Firebase\JWT\JWT;` estaba ubicada dentro del bloque `try`, lo cual causa un error sintáctico en PHP 8.
   - **Corrección:** Se movió la sentencia `use` al inicio del archivo después de `declare(strict_types=1);`.

---

## 5. Estado de la Suite de Pruebas Automáticas

- `composer test:regression` → **22 tests, 53 assertions, Exit Code: 0**
- `composer test:logistica` → **182 tests, 523 assertions, Exit Code: 0**
- `composer test` → **204 tests, 576 assertions, Exit Code: 0**

---

## 6. Conclusión de QA

Todos los flujos críticos de la Fase 3D fueron validados exitosamente en el entorno de staging local (`paquetes_apppack`). La integridad del negocio se mantiene 100% intacta (`pedidos.id_estado` no sufrió cambios).
