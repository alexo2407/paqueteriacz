# API CRM Endpoints - Documentación

## Autenticación

Todos los endpoints requieren JWT en el header:
```
Authorization: Bearer <token>
```

## Endpoints

### 1. POST /api/crm/leads
**Descripción**: Recibe leads de proveedores (individual o batch)  
**Rol permitido**: Proveedor, Administrador  
**Response**: 202 Accepted (procesamiento async)

**Request individual**:
```json
{
  "lead": {
    "proveedor_lead_id": "PR-12345",
    "nombre": "Juan Pérez",
    "telefono": "+50512345678",
    "producto": "Laptop Dell",
    "precio": 500.00,
    "fecha_hora": "2025-01-15 10:30:00",
    "cliente_id": 5
  }
}
```

**Request batch**:
```json
{
  "leads": [
    {
      "proveedor_lead_id": "PR-001",
      "nombre": "María García",
      "telefono": "+50599999999",
      "fecha_hora": "2025-01-15 09:00:00"
    },
    {
      "proveedor_lead_id": "PR-002",
      "nombre": "Carlos López",
      "telefono": "+50588888888",
      "fecha_hora": "2025-01-15 09:15:00"
    }
  ]
}
```

**Response 202**:
```json
{
  "success": true,
  "message": "Lead(s) aceptado(s) para procesamiento",
  "accepted": 1,
  "inbox_id": 123
}
```

### 2. POST /api/crm/leads/{id}/estado
**Descripción**: Actualiza estado de un lead  
**Rol permitido**: Cliente (owner), Administrador  
**Response**: 200 OK

**Request**:
```json
{
  "estado": "Aprovado",
  "observaciones": "Cliente confirmó recepción"
}
```

**Response 200**:
```json
{
  "success": true,
  "message": "Estado actualizado a APROBADO",
  "estado_anterior": "EN_ESPERA",
  "estado_nuevo": "APROBADO"
}
```

**Estados válidos**:
- `CANCELADO`
- `APROBADO` (aliases: "Aprovado", "Approved")
- `CONFIRMADO`
- `EN_TRANSITO`
- `EN_BODEGA`
- `EN_ESPERA` (default inicial)

**Transiciones permitidas**:
- EN_ESPERA → APROBADO, CANCELADO
- APROBADO → CONFIRMADO, CANCELADO
- CONFIRMADO → EN_TRANSITO, CANCELADO
- EN_TRANSITO → EN_BODEGA, CANCELADO
- EN_BODEGA → CANCELADO
- CANCELADO → (ninguna)

### 3. GET /api/crm/leads
**Descripción**: Lista leads con filtros y paginación  
**Rol permitido**: Proveedor (sus leads), Cliente (sus leads), Admin (todos)  
**Response**: 200 OK

**Query params**:
- `page` (int, default: 1)
- `limit` (int, max: 100, default: 50)
- `estado` (string, opcional)
- `fecha_desde` (date, opcional)
- `fecha_hasta` (date, opcional)

**Ejemplo**:
```
GET /api/crm/leads?page=1&limit=10&estado=APROBADO
```

**Response 200**:
```json
{
  "success": true,
  "total": 150,
  "page": 1,
  "limit": 10,
  "leads": [
    {
      "id": 1,
      "proveedor_id": 3,
      "cliente_id": 5,
      "proveedor_lead_id": "PR-12345",
      "nombre": "Juan Pérez",
      "telefono": "+50512345678",
      "producto": "Laptop Dell",
      "precio": "500.00",
      "estado_actual": "APROBADO",
      "fecha_hora": "2025-01-15 10:30:00",
      "created_at": "2025-01-15 10:31:00"
    }
  ]
}
```

### 4. GET /api/crm/leads/{id}
**Descripción**: Ver detalle de un lead  
**Rol permitido**: Proveedor (owner), Cliente (owner), Admin  
**Response**: 200 OK

**Response 200**:
```json
{
  "success": true,
  "lead": {
    "id": 1,
    "proveedor_id": 3,
    "cliente_id": 5,
    "proveedor_lead_id": "PR-12345",
    "nombre": "Juan Pérez",
    "telefono": "+50512345678",
    "producto": "Laptop Dell",
    "precio": "500.00",
    "estado_actual": "APROBADO",
    "duplicado": 0,
    "fecha_hora": "2025-01-15 10:30:00",
    "created_at": "2025-01-15 10:31:00",
    "updated_at": "2025-01-15 11:00:00"
  }
}
```

### 5. GET /api/crm/leads/{id}/timeline
**Descripción**: Ver historial de cambios de estado  
**Rol permitido**: Proveedor (owner), Cliente (owner), Admin  
**Response**: 200 OK

**Response 200**:
```json
{
  "success": true,
  "lead_id": 1,
  "timeline": [
    {
      "id": 2,
      "lead_id": 1,
      "estado_anterior": "EN_ESPERA",
      "estado_nuevo": "APROBADO",
      "actor_user_id": 5,
      "actor_nombre": "Cliente Demo",
      "observaciones": "Cliente confirmó recepción",
      "created_at": "2025-01-15 11:00:00"
    },
    {
      "id": 1,
      "lead_id": 1,
      "estado_anterior": null,
      "estado_nuevo": "EN_ESPERA",
      "actor_user_id": 1,
      "actor_nombre": "Sistema",
      "observaciones": "Lead creado",
      "created_at": "2025-01-15 10:31:00"
    }
  ]
}
```

### 6. GET /api/crm/metrics
**Descripción**: Métricas del sistema CRM  
**Rol permitido**: Administrador únicamente  
**Response**: 200 OK

**Response 200**:
```json
{
  "success": true,
  "metrics": {
    "leads": {
      "total": 150,
      "by_status": [
        {"estado_actual": "EN_ESPERA", "count": 45},
        {"estado_actual": "APROBADO", "count": 30},
        {"estado_actual": "CONFIRMADO", "count": 25}
      ]
    },
    "inbox": [
      {"status": "pending", "count": 5},
      {"status": "processed", "count": 120}
    ],
    "outbox": {
      "by_status": [
        {"status": "pending", "count": 3},
        {"status": "sent", "count": 200},
        {"status": "failed", "count": 2}
      ],
      "permanent_failed": 0
    },
    "recent": {
      "inbox": [...],
      "outbox": [...]
    }
  }
}
```

## Códigos de Error

| Código | Significado |
|--------|-------------|
| 200 | OK - Solicitud exitosa |
| 202 | Accepted - Encolado para procesamiento |
| 400 | Bad Request - Validación fallida |
| 401 | Unauthorized - Token inválido/faltante |
| 403 | Forbidden - Sin permisos |
| 404 | Not Found - Recurso no existe |
| 409 | Conflict - Duplicado (proveedor_lead_id) |
| 500 | Internal Server Error |

## Ejemplos cURL

Ver archivo `docs/CRM_CURL_EXAMPLES.md` para ejemplos completos.
