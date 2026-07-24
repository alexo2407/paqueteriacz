# Logística Operativa — Alcance V1

## Identificación

- **Proyecto:** Logística Operativa
- **Versión:** 1.0
- **Estado:** Borrador
- **Aplicación:** paqueteriacz
- **Base de datos:** paquetes_apppack (base existente)
- **Repositorio:** alexo2407/paqueteriacz
- **Rama de trabajo:** feature/logistica-operativa-sdd
- **Estrategia:** integración modular progresiva
- **Metodología:** Spec-Driven Development
- **Fecha inicio de Fase 0:** 2026-07-22

---

## Objetivo

Implementar dentro del sistema actual el control físico, operativo y financiero de la paquetería desde la recepción digital hasta la liquidación, manteniendo compatibilidad total con pedidos, inventario, API, CRM, forwarding, auditoría y seguimiento.

El módulo se integrará de forma aditiva, sin modificar ni renombrar ninguna estructura existente.

---

## Incluido en V1

1. Colectas de turno mañana y tarde.
2. Conciliación de pedidos digitales contra paquetes físicos.
3. Escaneo QR idempotente por UUID.
4. Recepción física en bodega.
5. Clasificación geográfica.
6. Ubicación física de incidencias.
7. Creación y asignación de rutas.
8. Sellado de rutas.
9. Manifiestos financieros versionados.
10. Entregas e incidencias en campo.
11. Retorno físico de paquetes.
12. Liquidación de rutas.
13. Custodia departamental.
14. Logística inversa.
15. Reintegro a Kardex cuando corresponda.

---

## Fuera de V1

1. GPS en tiempo real.
2. Mapa público del mensajero.
3. Tracking satelital.
4. Sustitución del módulo actual de pedidos.
5. Sustitución de la tabla `estados_pedidos`.
6. Renumeración de estados existentes.
7. Aplicación móvil nativa independiente.

---

## Restricciones

- La tabla `estados_pedidos` se mantiene como catálogo canónico y oficial.
- No renumerar estados existentes (los IDs actuales mueven inventario).
- Reutilizar estados actuales cuando exista equivalencia funcional.
- Agregar estados únicamente cuando no exista equivalencia y se justifique formalmente.
- Mantener detalles físicos, financieros y de custodia en tablas especializadas de Logística Operativa.
- No duplicar movimientos de inventario.
- No permitir cambios de estado fuera del servicio central (`PedidoService`).
- Toda migración futura debe ser aditiva e idempotente.
- Toda funcionalidad nueva debe iniciar desactivada (feature flag).
- Todo cambio productivo debe estar protegido por pruebas de regresión.
- Toda operación crítica deberá ejecutarse dentro de una transacción PDO.

---

## Primer módulo funcional futuro

**LO-COL-001** — Colecta y conciliación física.

---

## Información de contexto del sistema

> Datos obtenidos de inspección directa del código y la base de datos el 2026-07-22.

| Item | Valor |
|---|---|
| PHP versión real | 8.2.12 CLI |
| Base de datos | MariaDB/MySQL vía XAMPP |
| Schema productivo | `paquetes_apppack` |
| PHPUnit instalado | No |
| Pruebas existentes | Ninguna |
| Migraciones automáticas | No — scripts SQL manuales |
| Transacciones | PDO `beginTransaction()` / `commit()` / `rollback()` |
| Auditoría | `AuditoriaModel::registrar()` → tabla `auditoria_cambios` |
| Estados totales | 17 en `estados_pedidos` |
| .gitignore tests/ | Sí — `tests/` está ignorado (requiere corrección) |
