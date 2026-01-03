# Ejemplos de uso del endpoint asíncrono de actualización masiva

## 1. Enviar job asíncrono (sin límite de leads)

```bash
curl -X POST "http://localhost/paqueteriacz/api/crm/leads/bulk-status-async" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{
    "lead_ids": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    "estado": "aprobado",
    "observaciones": "Procesamiento masivo de campaña"
  }'
```

### Respuesta (202 Accepted - Inmediata):
```json
{
  "success": true,
  "job_id": "bulk_67891abc_1735798800",
  "status": "queued",
  "total_leads": 10,
  "message": "Job encolado para procesamiento",
  "check_status_url": "/api/crm/jobs/bulk_67891abc_1735798800"
}
```

---

## 2. Consultar estado del job

```bash
curl -X GET "http://localhost/paqueteriacz/api/crm/jobs/bulk_67891abc_1735798800" \
  -H "Authorization: Bearer <TOKEN>"
```

### Respuesta - Job en proceso:
```json
{
  "success": true,
  "job_id": "bulk_67891abc_1735798800",
  "status": "processing",
  "total_leads": 1000,
  "processed_leads": 450,
  "successful_leads": 448,
  "failed_leads": 2,
  "estado": "APROBADO",
  "progress_percent": 45.0,
  "created_at": "2026-01-02 23:30:00",
  "started_at": "2026-01-02 23:30:05",
  "completed_at": null
}
```

### Respuesta - Job completado:
```json
{
  "success": true,
  "job_id": "bulk_67891abc_1735798800",
  "status": "completed",
  "total_leads": 1000,
  "processed_leads": 1000,
  "successful_leads": 998,
  "failed_leads": 2,
  "estado": "APROBADO",
  "progress_percent": 100.0,
  "created_at": "2026-01-02 23:30:00",
  "started_at": "2026-01-02 23:30:05",
  "completed_at": "2026-01-02 23:30:15",
  "failed_details": [
    {"lead_id": 5, "error": "Sin permiso"},
    {"lead_id": 999, "error": "Lead no encontrado"}
  ]
}
```

---

## 3. Ejemplo con muchos leads (5000+)

```bash
# El cliente puede enviar miles de leads sin problema
curl -X POST "http://localhost/paqueteriacz/api/crm/leads/bulk-status-async" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TOKEN>" \
  -d '{
    "lead_ids": [1, 2, 3, ..., 5000],
    "estado": "contactado"
  }'
```

**Respuesta inmediata** (202), procesamiento en background.

---

## Iniciar el Worker

Para procesar los jobs, ejecutar el worker en background:

```powershell
# Windows
php C:\xampp\htdocs\paqueteriacz\cli\crm_bulk_worker.php

# En producción, usar un supervisor como supervisord o pm2
```

---

## Comparativa

| Endpoint | Límite | Tiempo Respuesta | Uso |
|----------|--------|------------------|-----|
| `/bulk-status` | 100 leads | 200-500ms | Actualizaciones pequeñas |
| `/bulk-status-async` | Sin límite | ~50ms | Actualizaciones grandes |

---

## Ventajas del Asíncrono

✅ **Sin bloqueos**: Múltiples clientes pueden enviar jobs simultáneamente  
✅ **Sin límites**: Envía 1000, 10000 o más leads  
✅ **Sin timeouts**: El worker procesa en background  
✅ **Progreso rastreable**: Consulta el avance en tiempo real  
✅ **Fairness**: Jobs se procesan en orden de llegada  
