# CRM Relay - Ejemplos cURL

Estos ejemplos asumen que tienes tokens JWT válidos en las variables:
- `$PROVEEDOR_TOKEN` - Token de un usuario Proveedor
- `$CLIENTE_TOKEN` - Token de un usuario Cliente  
- `$ADMIN_TOKEN` - Token de un usuario Administrador

## 1. Obtener Token JWT

```bash
# Login como proveedor
curl -X POST http://localhost/paqueteriacz/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "proveedor@example.com",
    "password": "password123"
  }'

# Response:
# {
#   "success": true,
#   "token": "eyJ0eXAiOiJKV1QiLCJhbG..."
# }

# Guardar token
export PROVEEDOR_TOKEN="eyJ0eXAiOiJKV1QiLCJhbG..."
```

## 2. POST /api/crm/leads (Individual)

```bash
curl -X POST http://localhost/paqueteriacz/api/crm/leads \
  -H "Authorization: Bearer $PROVEEDOR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lead": {
      "proveedor_lead_ ID": "PR-12345",
      "nombre": "Juan Pérez",
      "telefono": "+50512345678",
      "producto": "Laptop Dell",
      "precio": 500.00,
      "fecha_hora": "2025-01-15 10:30:00",
      "cliente_id": 5
    }
  }'

# Expected: 202 Accepted
# {
#   "success": true,
#   "message": "Lead(s) aceptado(s) para procesamiento",
#   "accepted": 1,
#   "inbox_id": 123
# }
```

## 3. POST /api/crm/leads (Batch)

```bash
curl -X POST http://localhost/paqueteriacz/api/crm/leads \
  -H "Authorization: Bearer $PROVEEDOR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
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
      },
      {
        "proveedor_lead_id": "PR-003",
        "nombre": "Ana Martínez",
        "telefono": "+50577777777",
        "fecha_hora": "2025-01-15 09:30:00"
      }
    ]
  }'

# Expected: 202 Accepted
# {
#   "success": true,
#   "message": "Lead(s) aceptado(s) para procesamiento",
#   "accepted": 3,
#   "inbox_id": 124
# }
```

## 4. Test Idempotencia (Reintento)

```bash
# Enviar el mismo lead dos veces
curl -X POST http://localhost/paqueteriacz/api/crm/leads \
  -H "Authorization: Bearer $PROVEEDOR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lead": {
      "proveedor_lead_id": "PR-DUPLICADO",
      "nombre": "Test Duplicado",
      "telefono": "+50512345678",
      "fecha_hora": "2025-01-15 10:00:00"
    }
  }'

# Primera vez: 202 Accepted
# Segunda vez (mismo payload): 200 OK o 202 con duplicated:true
```

## 5. GET /api/crm/leads (Listar)

```bash
# Listar todos los leads (como proveedor)
curl http://localhost/paqueteriacz/api/crm/leads \
  -H "Authorization: Bearer $PROVEEDOR_TOKEN"

# Con filtros
curl "http://localhost/paqueteriacz/api/crm/leads?page=1&limit=10&estado=APROBADO&fecha_desde=2025-01-01" \
  -H "Authorization: Bearer $PROVEEDOR_TOKEN"

# Expected: 200 OK
# {
#   "success": true,
#   "total": 150,
#   "page": 1,
#   "limit": 10,
#   "leads": [...]
# }
```

## 6. GET /api/crm/leads/{id} (Detalle)

```bash
# Ver detalle de un lead
curl http://localhost/paqueteriacz/api/crm/leads/1 \
  -H "Authorization: Bearer $PROVEEDOR_TOKEN"

# Expected: 200 OK
# {
#   "success": true,
#   "lead": {...}
# }
```

## 7. POST /api/crm/leads/{id}/estado (Actualizar)

```bash
# Cliente actualiza estado con alias "Aprovado"
curl -X POST http://localhost/paqueteriacz/api/crm/leads/1/estado \
  -H "Authorization: Bearer $CLIENTE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "estado": "Aprovado",
    "observaciones": "Cliente confirmó recepción del producto"
  }'

# Expected: 200 OK
# {
#   "success": true,
#   "message": "Estado actualizado a APROBADO",
#   "estado_anterior": "EN_ESPERA",
#   "estado_nuevo": "APROBADO"
# }
```

## 8. Test Transición Inválida

```bash
# Intentar saltar de EN_ESPERA a EN_TRANSITO (no permitido)
curl -X POST http://localhost/paqueteriacz/api/crm/leads/1/estado \
  -H "Authorization: Bearer $CLIENTE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "estado": "EN_TRANSITO"
  }'

# Expected: 400 Bad Request
# {
#   "success": false,
#   "message": "Transición no permitida de EN_ESPERA a EN_TRANSITO"
# }
```

## 9. GET /api/crm/leads/{id}/timeline

```bash
# Ver historial de cambios
curl http://localhost/paqueteriacz/api/crm/leads/1/timeline \
  -H "Authorization: Bearer $CLIENTE_TOKEN"

# Expected: 200 OK
# {
#   "success": true,
#   "lead_id": 1,
#   "timeline": [
#     {
#       "id": 2,
#       "estado_anterior": "EN_ESPERA",
#       "estado_nuevo": "APROBADO",
#       "actor_nombre": "Cliente Demo",
#       "observaciones": "Cliente confirmó recepción",
#       "created_at": "2025-01-15 11:00:00"
#     }
#   ]
# }
```

## 10. GET /api/crm/metrics (Admin)

```bash
# Ver métricas del sistema
curl http://localhost/paqueteriacz/api/crm/metrics \
  -H "Authorization: Bearer $ADMIN_TOKEN"

# Expected: 200 OK
# {
#   "success": true,
#   "metrics": {
#     "leads": {...},
#     "inbox": [...],
#     "outbox": {...}
#   }
# }
```

## 11. Test Flujo Completo End-to-End

```bash
#!/bin/bash
set -e

echo "=== CRM Relay - Test E2E ==="

# 1. Proveedor envia lead
echo "1. Proveedor envia lead..."
RESPONSE=$(curl -s -X POST http://localhost/paqueteriacz/api/crm/leads \
  -H "Authorization: Bearer $PROVEEDOR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "lead": {
      "proveedor_lead_id": "E2E-TEST-001",
      "nombre": "Test E2E",
      "telefono": "+50512345678",
      "fecha_hora": "'$(date '+%Y-%m-%d %H:%M:%S')'"
    }
  }')

echo "Response: $RESPONSE"
INBOX_ID=$(echo $RESPONSE | jq -r '.inbox_id')
echo "Inbox ID: $INBOX_ID"

# 2. Ejecutar worker una vez
echo ""
echo "2. Procesando inbox con worker..."
php cli/crm_worker.php --once

# 3. Verificar lead creado
echo ""
echo "3. Verificando lead creado..."
sleep 1
LEADS=$(curl -s http://localhost/paqueteriacz/api/crm/leads \
  -H "Authorization: Bearer $PROVEEDOR_TOKEN")

LEAD_ID=$(echo $LEADS | jq -r '.leads[0].id')
echo "Lead ID: $LEAD_ID"

# 4. Cliente actualiza estado
echo ""
echo "4. Cliente actualiza estado..."
curl -s -X POST http://localhost/paqueteriacz/api/crm/leads/$LEAD_ID/estado \
  -H "Authorization: Bearer $CLIENTE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "estado": "APROBADO",
    "observaciones": "Test E2E aprobado"
  }'

# 5. Ver timeline
echo ""
echo "5. Verificando timeline..."
curl -s http://localhost/paqueteriacz/api/crm/leads/$LEAD_ID/timeline \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .

echo ""
echo "=== Test E2E completado ==="
```

## 12. Verificar Cola Outbox

```bash
# Ver mensajes pendientes en outbox (SQL)
mysql -u root -p paqueteria -e "SELECT * FROM crm_outbox WHERE status='pending' LIMIT 5;"

# Ver mensajes fallidos
mysql -u root -p paqueteria -e "SELECT id, event_type, attempts, last_error FROM crm_outbox WHERE status='failed';"
```

## 13. Simular Fallo de Webhook

```bash
# Configurar integración con URL inválida
mysql -u root -p paqueteria -e "
  UPDATE crm_integrations 
  SET webhook_url = 'http://localhost:9999/webhook-invalido'
  WHERE user_id = 5 AND kind = 'cliente';
"

# Cliente actualiza estado (encolará outbox)
curl -X POST http://localhost/paqueteriacz/api/crm/leads/1/estado \
  -H "Authorization: Bearer $CLIENTE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"estado": "CONFIRMADO"}'

# Ejecutar worker (fallará y hará backoff)
php cli/crm_worker.php --once

# Verificar reintentos
mysql -u root -p paqueteria -e "
  SELECT id, attempts, next_retry_at, last_error 
  FROM crm_outbox 
  WHERE status='failed' 
  ORDER BY created_at DESC 
  LIMIT 5;
"
```

## Checklist de Producción

Una vez completados estos tests, verificar:

- [ ] Todas las operaciones CRUD funcionan sin errores
- [ ] Idempotencia confirmada (reintentos no duplican)
- [ ] Worker procesa inbox y outbox correctamente
- [ ] Estados se normalizan ("Aprovado" → "APROBADO")
- [ ] Transiciones inválidas son rechazadas
- [ ] Ownership funciona (proveedor/cliente solo ven sus leads)
- [ ] Admin tiene acceso completo
- [ ] Métricas endpoint retorna datos válidos
- [ ] Outbox hace reintentos con backoff
- [ ] Logs de worker son claros
- [ ] JWT validation funciona en todos los endpoints
