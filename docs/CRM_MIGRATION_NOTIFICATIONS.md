# CRM - Migración a Sistema de Notificaciones Internas

## ✅ Cambios Completados

### 📊 Base de Datos
- [x] Eliminada tabla `crm_outbox`
- [x] Eliminada tabla `crm_integrations`
- [x] Creada tabla `crm_notifications`

### 📁 Archivos Nuevos
- [x] `modelo/crm_notification.php` - Modelo para notificaciones internas

### 📝 Archivos Modificados

#### 1. Services
- [x] `services/crm_inbox_service.php`
  - Reemplazado `CrmOutboxModel` por `CrmNotificationModel`
  - Eliminada verificación de integración (ya no se necesita)
  - Crea notificaciones internas al procesar leads

#### 2. API Endpoints
- [x] `api/crm/lead_status.php`
  - Usa `CrmNotificationModel` en lugar de `CrmOutboxModel`
  
- [x] `api/crm/lead_bulk_status.php`
  - Batch inserts a `crm_notifications` en lugar de `crm_outbox`
  
#### 3. Workers
- [x] `cli/crm_bulk_worker.php`
  - Batch inserts a `crm_notifications`
  
- [x] `cli/crm_worker.php`
  - Eliminado procesamiento de outbox
  - Solo procesa inbox (leads entrantes)

#### 4. Vistas (Actualizado UI)
- [x] `vista/modulos/crm/integraciones_crear.php`
  - Campo URL cambiado a `type="text"` para permitir localhost
  - Agregado checkbox para permitir URLs internas
  - Ejemplos de URLs añadidos

- [x] `vista/modulos/crm/integraciones_editar.php`
  - Campo URL actualizado

### ❌ Archivos que ya NO se usan (puedes eliminar opcionalmente)
- `services/crm_outbox_service.php` - Ya no se necesita
- `modelo/crm_outbox.php` - Ya no se necesita
- `modelo/crm_integration.php` - Ya no se necesita

### 🔄 Flujo Anterior vs Nuevo

**ANTES (Webhooks Externos):**
```
Proveedor → API → crm_inbox → Worker → crm_leads
                                          ↓
                                     crm_outbox → HTTP POST a URL externa
                                          ↓
                                    Cliente recibe en su servidor
```

**AHORA (Notificaciones Internas):**
```
Proveedor → API → crm_inbox → Worker → crm_leads
                                          ↓
                                    crm_notifications
                                          ↓
                            Cliente ve en su panel web (bandeja)
```

## 📋 Próximos Pasos

### 1. Crear Vista de Notificaciones
Necesitas crear:
- `vista/modulos/crm/notificaciones.php` - Bandeja de entrada
- `controlador/crm.php` - Agregar método `notificaciones()`
- `header.php` - Agregar campana con contador

### 2. API para Notificaciones
Crear endpoint:
- `GET /api/crm/notifications` - Listar notificaciones del usuario
- `POST /api/crm/notifications/{id}/read` - Marcar como leída
- `POST /api/crm/notifications/read-all` - Marcar todas como leídas

### 3. Cleanup Opcional
Eliminar archivos obsoletos:
```bash
rm services/crm_outbox_service.php
rm modelo/crm_outbox.php
rm modelo/crm_integration.php
```

## 🧪 Testing

### Probar creación de notificaciones:
```bash
# 1. Ejecutar worker
php cli/crm_worker.php --once

# 2. Enviar lead de prueba (vía API)
# 3. Verificar que se creó en crm_notifications:
SELECT * FROM crm_notifications ORDER BY created_at DESC LIMIT 5;
```

### Probar bulk job:
```bash
# 1. Ejecutar bulk worker
php cli/crm_bulk_worker.php

# 2. Hacer actualización masiva (vía API)
# 3. Verificar notificaciones creadas
```

## 💡 Beneficios del Nuevo Sistema

✅ **Más simple** - No hay webhooks HTTP externos  
✅ **Más rápido** - No hay red involucrada  
✅ **Más confiable** - No hay timeouts o errores HTTP  
✅ **Más seguro** - Todo queda en tu base de datos  
✅ **Mejor UX** - Los usuarios ven todo en un solo lugar  
✅ **Auditable** - Sabes quién leyó qué y cuándo  

## ⚠️ Notas Importantes

- El sistema de integraciones (tabla crm_integrations) fue eliminado
- Ya no se configuran URLs de webhook por usuario
- Todas las notificaciones son internas ahora
- Los workers siguen funcionando igual para procesar la cola
