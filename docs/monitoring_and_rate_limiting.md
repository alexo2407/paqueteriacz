# Guía de Monitoreo y Rate Limiting

## 📊 Monitoreo de Jobs

### Script de Limpieza Automática

**Ejecutar:** `php cli/crm_jobs_cleanup.php`

**Qué hace:**
1. ✅ Elimina jobs completados hace >7 días
2. ✅ Elimina jobs fallidos hace >30 días  
3. ✅ Marca como fallidos jobs procesando >1 hora (worker caído)
4. ⚠️ Alerta sobre jobs en cola >1 hora (worker no está corriendo)
5. 📊 Muestra estadísticas de las últimas 24 horas

**Programar diariamente:**
```powershell
# Windows Task Scheduler - ejecutar a las 3 AM
schtasks /create /tn "CRM Jobs Cleanup" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\paqueteriacz\cli\crm_jobs_cleanup.php" /sc daily /st 03:00
```

### Ejemplo de Output

```
[2026-01-02 23:45:00] Iniciando limpieza de jobs antiguos...
✓ Jobs completados eliminados: 45
✓ Jobs fallidos eliminados: 3
✓ Jobs con timeout marcados como fallidos: 0
⚠️  ALERTA: 2 jobs en cola por más de 1 hora!
   → Verificar que el worker esté corriendo.

📊 Estadísticas últimas 24 horas:
   completed: 156 jobs (promedio 2.34s)
   failed: 4 jobs (promedio 1.12s)

📦 Total de jobs en la tabla: 85

[2026-01-02 23:45:01] Limpieza completada.
```

---

## 🚦 Rate Limiting

### 5 Estrategias Implementadas

#### 1. **Jobs Pendientes** (Recomendado)
```php
// Máximo 10 jobs en cola/procesando al mismo tiempo
max_pending_jobs: 10
```

**Previene:** Cliente saturando la cola con 1000 jobs

---

#### 2. **Jobs por Día**
```php
// Máximo 100 jobs por usuario por día
max_jobs_per_day: 100
```

**Previene:** Abuso persistente durante el día

---

#### 3. **Tamaño del Job**
```php
// Máximo 10,000 leads por job individual
max_leads_per_job: 10000
```

**Previene:** Jobs gigantescos que saturan el worker

---

#### 4. **Leads Totales por Día**
```php
// Máximo 50,000 leads procesados por usuario por día
max_leads_per_day: 50000
```

**Previene:** Cliente procesando millones de leads

---

#### 5. **Cooldown (Throttling)**
```php
// Mínimo 30 segundos entre jobs
cooldown_seconds: 30
```

**Previene:** Spam de requests

---

### Habilitar Rate Limiting

**En `lead_bulk_status_async.php`, descomentar líneas 106-125:**

```php
// Antes (sin rate limiting)
/*
require_once __DIR__ . '/../../utils/rate_limiter.php';
$rateLimitCheck = enforceRateLimits($userId, count($leadIds));
...
*/

// Después (con rate limiting)
require_once __DIR__ . '/../../utils/rate_limiter.php';
$rateLimitCheck = enforceRateLimits($userId, count($leadIds));

if (!$rateLimitCheck['allowed']) {
    http_response_code(429);
    echo json_encode([
        'error' => 'rate_limit_exceeded',
        'message' => $rateLimitCheck['message'],
        'retry_after' => $rateLimitCheck['retry_after']
    ]);
    exit;
}
```

---

### Respuestas de Rate Limit

#### Límite de Jobs Pendientes Alcanzado
```json
{
  "success": false,
  "error": "rate_limit_exceeded",
  "message": "Límite alcanzado: tienes 10 jobs pendientes (máximo 10)",
  "retry_after": 60
}
```

#### Límite Diario Alcanzado
```json
{
  "success": false,
  "error": "rate_limit_exceeded",
  "message": "Límite diario alcanzado: has creado 100 jobs hoy (máximo 100)",
  "reset_at": "2026-01-03 00:00:00"
}
```

#### Cooldown Activo
```json
{
  "success": false,
  "error": "rate_limit_exceeded",
  "message": "Por favor espera 15 segundos antes de crear otro job",
  "retry_after": 15
}
```

---

## 🎛️ Personalizar Límites por Rol

Puedes ajustar límites según el rol del usuario:

```php
// En rate_limiter.php, modificar enforceRateLimits()

function enforceRateLimits($userId, $leadCount) {
    // Obtener rol del usuario
    $isAdmin = isAdmin($userId);
    
    if ($isAdmin) {
        // Admins: límites más altos
        $limits = [
            'max_pending_jobs' => 50,
            'max_jobs_per_day' => 1000,
            'max_leads_per_job' => 50000,
            'max_leads_per_day' => 500000,
            'cooldown_seconds' => 0 // sin cooldown
        ];
    } else {
        // Clientes regulares
        $limits = [
            'max_pending_jobs' => 10,
            'max_jobs_per_day' => 100,
            'max_leads_per_job' => 10000,
            'max_leads_per_day' => 50000,
            'cooldown_seconds' => 30
        ];
    }
    
    // ... resto de validaciones
}
```

---

## 📈 Monitoreo en Tiempo Real

### Query para ver estado actual

```sql
-- Jobs por estado
SELECT 
    status,
    COUNT(*) as total,
    SUM(total_leads) as leads_totales
FROM crm_bulk_jobs
WHERE DATE(created_at) = CURDATE()
GROUP BY status;

-- Top usuarios por volumen
SELECT 
    user_id,
    COUNT(*) as jobs_hoy,
    SUM(total_leads) as leads_hoy
FROM crm_bulk_jobs
WHERE DATE(created_at) = CURDATE()
GROUP BY user_id
ORDER BY leads_hoy DESC
LIMIT 10;

-- Jobs atascados
SELECT 
    id, user_id, total_leads, status,
    TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_en_cola
FROM crm_bulk_jobs
WHERE status = 'queued'
AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
ORDER BY created_at ASC;
```

---

## 🎯 Recomendaciones

### Para Empezar
1. ✅ Programa limpieza diaria
2. 🔒 Habilita solo rate limiting básico (jobs pendientes)
3. 📊 Monitorea durante 1 semana

### Para Producción
1. 🔒 Habilita todas las estrategias de rate limiting
2. 📧 Agrega notificaciones por email en script cleanup
3. 📊 Crea dashboard de métricas
4. 🔄 Ajusta límites según uso real

---

## ⚠️ Señales de Problemas

### Worker NO está corriendo
- Jobs en `queued` por >1 hora
- Script cleanup alerta constante

### Cliente abusivo
- Múltiples 429 responses
- Jobs fallidos por ownership

### Configuración incorrecta
- Muchos jobs con timeout
- Worker se detiene frecuentemente
