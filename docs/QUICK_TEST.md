# Quick Test - Sistema de Bulk Updates

## Test 1: Verificar Sintaxis de Todos los Archivos

```powershell
php -l c:\xampp\htdocs\paqueteriacz\api\crm\lead_bulk_status.php
php -l c:\xampp\htdocs\paqueteriacz\api\crm\lead_bulk_status_async.php
php -l c:\xampp\htdocs\paqueteriacz\api\crm\job_status.php
php -l c:\xampp\htdocs\paqueteriacz\cli\crm_bulk_worker.php
php -l c:\xampp\htdocs\paqueteriacz\cli\crm_jobs_cleanup.php
php -l c:\xampp\htdocs\paqueteriacz\utils\rate_limiter.php
```

**Resultado esperado:** "No syntax errors detected" en todos

---

## Test 2: Verificar Tabla crm_bulk_jobs

```sql
USE paquetes_apppack;
SHOW CREATE TABLE crm_bulk_jobs;
```

**Resultado esperado:** Tabla existe con todas las columnas

---

## Test 3: Verificar Índices

```sql
SHOW INDEX FROM crm_leads WHERE Key_name LIKE 'idx_crm_leads%';
```

**Resultado esperado:** Al menos 3 índices creados

---

## Test 4: Test del Worker (Sin Jobs)

```powershell
# ctrl+C para detener después de 5 segundos
php c:\xampp\htdocs\paqueteriacz\cli\crm_bulk_worker.php
```

**Resultado esperado:**
```
[2026-01-02 23:30:00] CRM Bulk Jobs Worker iniciado
(esperando jobs...)
```

---

## Test 5: Test de Limpieza

```powershell
php c:\xampp\htdocs\paqueteriacz\cli\crm_jobs_cleanup.php
```

**Resultado esperado:**
```
[...] Iniciando limpieza de jobs antiguos...
✓ Jobs completados eliminados: 0
✓ Jobs fallidos eliminados: 0
✓ Jobs con timeout marcados como fallidos: 0
📊 Estadísticas últimas 24 horas:
📦 Total de jobs en la tabla: X
[...] Limpieza completada.
```

---

## Test 6: Endpoint Síncrono (cURL)

**Requiere:** JWT token válido

```bash
curl -X POST "http://localhost/paqueteriacz/api/crm/leads/bulk-status" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TU_TOKEN>" \
  -d '{
    "lead_ids": [1, 2, 3],
    "estado": "contactado"
  }'
```

**Resultado esperado:**
```json
{
  "success": true,
  "message": "3 de 3 leads actualizados exitosamente",
  "updated": 3,
  "failed": 0
}
```

---

## Test 7: Endpoint Asíncrono (cURL)

```bash
curl -X POST "http://localhost/paqueteriacz/api/crm/leads/bulk-status-async" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <TU_TOKEN>" \
  -d '{
    "lead_ids": [1, 2, 3],
    "estado": "aprobado"
  }'
```

**Resultado esperado:**
```json
{
  "success": true,
  "job_id": "bulk_...",
  "status": "queued",
  "total_leads": 3
}
```

---

## Test 8: Consultar Status de Job

**Usar job_id del test anterior:**

```bash
curl -X GET "http://localhost/paqueteriacz/api/crm/jobs/<JOB_ID>" \
  -H "Authorization: Bearer <TU_TOKEN>"
```

**Resultado esperado (si worker está corriendo):**
```json
{
  "success": true,
  "status": "completed",
  "total_leads": 3,
  "successful_leads": 3,
  "progress_percent": 100
}
```

---

## Test 9: Rate Limiting

**Enviar 11 jobs rápidamente (exceder límite de 10 pending):**

```bash
for i in {1..11}; do
  curl -X POST "http://localhost/paqueteriacz/api/crm/leads/bulk-status-async" \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer <TU_TOKEN>" \
    -d '{"lead_ids":[1,2,3],"estado":"contactado"}'
  echo "Request $i"
done
```

**Resultado esperado:** Primeros 10 acepted, el 11º rechazado con 429

---

## Test 10: Sistema Completo (E2E)

1. ✅ Iniciar worker en terminal 1
2. ✅ Enviar job async con 10 leads
3. ✅ Consultar status cada 2 segundos
4. ✅ Verificar que cambia de "queued" → "processing" → "completed"
5. ✅ Verificar en BD que leads cambiaron de estado
6. ✅ Verificar que se creó historial

---

## Checklist de Producción

Antes de lanzar a producción:

- [ ] Worker corriendo como servicio Windows
- [ ] Limpieza programada diariamente
- [ ] Rate limiting configurado según necesidades
- [ ] Índices de DB creados
- [ ] Pruebas con volumen real (1000+ leads)
- [ ] Monitoreo de logs configurado
- [ ] Documentación compartida con el equipo

---

## Troubleshooting Rápido

### Worker no procesa jobs
```sql
-- Verificar jobs en cola
SELECT * FROM crm_bulk_jobs WHERE status = 'queued';
```

### Jobs con timeout
```sql
-- Ver jobs procesando por más de 1 hora
SELECT * FROM crm_bulk_jobs 
WHERE status = 'processing' 
AND started_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Marcarlos como fallidos manualmente
UPDATE crm_bulk_jobs 
SET status = 'failed', error_message = 'Manual timeout' 
WHERE id = 'JOB_ID';
```

### Rate limit muy restrictivo
```php
// En utils/rate_limiter.php
// Subir límites temporalmente para testing
'max_pending_jobs' => 50,  // era 10
```
