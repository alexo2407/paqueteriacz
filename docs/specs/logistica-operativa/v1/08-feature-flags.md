# Feature Flags — Logística Operativa

> Fase 1 · Implementado en `config/config.php` y `services/LogisticaOperativaFlags.php`

**Principio:** ningún flag modifica datos, estados ni inventario por sí solo.
Solo controlan qué funcionalidades futuras estarán activas.

---

## Flags y valores por defecto

| Flag | Defecto | Descripción |
|------|---------|-------------|
| `LOGISTICA_OPERATIVA_ENABLED` | `false` | Interruptor principal. Con `false` todos los demás son inoperantes. |
| `LOGISTICA_OPERATIVA_SHADOW_MODE` | `true` | Registra sin efectos laterales. Requiere `ENABLED=true`. |
| `LOGISTICA_OPERATIVA_UPDATE_STATES` | `false` | Permite cambiar `id_estado` en pedidos. |
| `LOGISTICA_OPERATIVA_INVENTORY_ENABLED` | `false` | Permite movimientos en Kardex. |
| `LOGISTICA_OPERATIVA_ROUTES_ENABLED` | `false` | Habilita creación de rutas (puede operar en shadow). |
| `LOGISTICA_OPERATIVA_SETTLEMENT_ENABLED` | `false` | Habilita liquidación de rutas. |

---

## Dependencias entre flags

```
ENABLED=false  → todos los métodos devuelven false (inoperantes)

ENABLED=true, SHADOW_MODE=true
  shadowMode()        = true
  canUpdateStates()   = false  (bloqueado por shadow)
  inventoryEnabled()  = false  (bloqueado por shadow)
  settlementEnabled() = false  (bloqueado por shadow)
  routesEnabled()     = depende de ROUTES_ENABLED

ENABLED=true, SHADOW_MODE=false
  canUpdateStates()   = valor de UPDATE_STATES
  inventoryEnabled()  = valor de INVENTORY_ENABLED
  settlementEnabled() = valor de SETTLEMENT_ENABLED
  routesEnabled()     = valor de ROUTES_ENABLED
```

---

## Activación gradual

Activar **un flag por despliegue**, en este orden:

1. `ENABLED=true` + `SHADOW_MODE=true` → modo observación (Fase 2)
2. `ROUTES_ENABLED=true` → rutas en shadow (Fase 5)
3. `SHADOW_MODE=false` → efectos reales activos (Fase 10 piloto)
4. `UPDATE_STATES=true` → cambios de estado (Fase 10)
5. `SETTLEMENT_ENABLED=true` → liquidaciones (Fase 10)
6. `INVENTORY_ENABLED=true` → logística inversa (Fase 9)

---

## Rollback

Revertir el flag en `config/config.php` y reiniciar Apache.
No requiere migración ni cambio de base de datos.

---

## Nota técnica

`phpunit.xml` inyecta los flags como strings (`"false"`, `"true"`).
`resolveFlag()` en la clase usa `filter_var(FILTER_VALIDATE_BOOLEAN)` para
interpretar correctamente las cadenas, evitando que `(bool)"false" === true`.
