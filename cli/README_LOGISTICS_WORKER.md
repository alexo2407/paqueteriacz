# Logistics Worker - Guía de Uso

## Descripción

Worker CLI para procesar trabajos asíncronos del módulo de logística.
Procesa la cola de trabajos cada 3 segundos.

## Tipos de Trabajos Soportados

1. **generar_guia** - Genera guías/etiquetas de envío
2. **actualizar_tracking** - Actualiza estados desde APIs de paqueterías
3. **validar_direccion** - Valida y normaliza direcciones con geocodificación
4. **notificar_estado** - Envía notificaciones de cambio de estado por email/SMS

## Modos de Ejecución

### Modo `--once` (Para Cron)
Procesa la cola una sola vez y termina. Ideal para ejecutar via cron.

```bash
php cli/logistics_worker.php --once
```

### Modo `--loop` (Para Daemon)
Loop infinito que procesa cada 3 segundos. Ideal para systemd/supervisor.

```bash
php cli/logistics_worker.php --loop
```

## Configuración con Cron

Ejecutar cada minuto:

```bash
* * * * * cd /xampp/htdocs/paqueteriacz && php cli/logistics_worker.php --once >> logs/logistics_worker.log 2>&1
```

## Configuración con Systemd (Recomendado para Producción)

Crear archivo `/etc/systemd/system/logistics-worker.service`:

```ini
[Unit]
Description=Logistics Worker
After=mariadb.service
Requires=mariadb.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/xampp/htdocs/paqueteriacz
ExecStart=/usr/bin/php /xampp/htdocs/paqueteriacz/cli/logistics_worker.php --loop
Restart=always
RestartSec=5
StandardOutput=append:/var/log/logistics-worker.log
StandardError=append:/var/log/logistics-worker-error.log

[Install]
WantedBy=multi-user.target
```

Habilitar y arrancar:

```bash
sudo systemctl enable logistics-worker
sudo systemctl start logistics-worker
sudo systemctl status logistics-worker
```

Ver logs:

```bash
sudo journalctl -u logistics-worker -f
```

## Configuración con Supervisor

Crear `/etc/supervisor/conf.d/logistics-worker.conf`:

```ini
[program:logistics-worker]
command=/usr/bin/php /xampp/htdocs/paqueteriacz/cli/logistics_worker.php --loop
directory=/xampp/htdocs/paqueteriacz
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/logistics-worker.log
```

Recargar supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status logistics-worker
```

## Uso Programático

### Encolar un trabajo manualmente

```php
<?php
require_once 'services/LogisticsQueueService.php';

// Encolar generación de guía
$result = LogisticsQueueService::queue('generar_guia', $pedidoId);

// Encolar validación de dirección
$result = LogisticsQueueService::queue('validar_direccion', $pedidoId);

// Encolar actualización de tracking
$result = LogisticsQueueService::queue('actualizar_tracking', $pedidoId, [
    'paqueteria' => 'fedex'
]);

// Encolar notificación de cambio de estado
$result = LogisticsQueueService::queue('notificar_estado', $pedidoId, [
    'estado_anterior' => 'pendiente',
    'estado_nuevo' => 'en_transito'
]);
```

### Verificar estado de la cola

```php
<?php
require_once 'services/LogisticsQueueService.php';

// Obtener métricas
$metricas = LogisticsQueueService::obtenerMetricas();
print_r($metricas);

// Contar trabajos por estado
$pendientes = LogisticsQueueService::contarPorEstado('pending');
$completados = LogisticsQueueService::contarPorEstado('completed');
$fallidos = LogisticsQueueService::contarPorEstado('failed');

echo "Pendientes: {$pendientes}\n";
echo "Completados: {$completados}\n";
echo "Fallidos: {$fallidos}\n";
```

## Monitoreo

El worker escribe logs en:
- **Stdout**: Procesamiento normal (processed/failed counts)
- **error_log**: Errores via `error_log()` de PHP

Para monitorear en tiempo real:

```bash
# Con systemd
sudo journalctl -u logistics-worker -f

# Con supervisor
sudo tail -f /var/log/logistics-worker.log

# Con cron
tail -f logs/logistics_worker.log
```

## Detención

### Modo --loop con systemd:
```bash
sudo systemctl stop logistics-worker
```

### Modo --loop con supervisor:
```bash
sudo supervisorctl stop logistics-worker
```

### Modo --loop manual:
Presionar `Ctrl+C` o enviar señal:
```bash
kill -SIGTERM <PID>
```

### Modo --once:
No requiere detención, termina automáticamente.

## Troubleshooting

### Worker no procesa trabajos
1. Verificar que MariaDB esté corriendo
2. Verificar credenciales en `config/config.php`
3. Verificar que tabla `logistics_queue` exista
4. Revisar permisos del usuario que ejecuta el worker

### Trabajos quedan en estado 'processing'
1. Verificar logs del worker para errores
2. Resetear trabajos manualmente:
```php
LogisticsQueueService::resetear($jobId);
```

### CPU/Memoria alta
1. Reducir `$pollInterval` en el código (línea 21)
2. Limitar cantidad de trabajos procesados por iteración (parámetro `$limit` en línea 36)

## Mantenimiento

### Limpiar trabajos completados antiguos
```sql
-- Borrar trabajos completados > 30 días
DELETE FROM logistics_queue 
WHERE status='completed' 
AND processed_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

O usar el método del servicio:
```php
<?php
require_once 'services/LogisticsQueueService.php';
$eliminados = LogisticsQueueService::limpiarCompletados(30);
echo "Trabajos eliminados: {$eliminados}\n";
```

### Ver trabajos fallidos
```sql
SELECT * FROM logistics_queue 
WHERE status = 'failed' 
ORDER BY updated_at DESC 
LIMIT 10;
```

### Reintentar trabajos fallidos específicos
```php
<?php
require_once 'services/LogisticsQueueService.php';
LogisticsQueueService::resetear($jobId);
```

## Extender con Nuevos Procesadores

Para agregar un nuevo tipo de trabajo:

1. Crear nuevo procesador en `cli/processors/MiNuevoProcessor.php`:

```php
<?php
require_once __DIR__ . '/BaseProcessor.php';

class MiNuevoProcessor extends BaseProcessor {
    public function process($job) {
        // Implementar lógica
        return [
            'success' => true,
            'message' => 'Trabajo procesado'
        ];
    }
}
```

2. Agregar el tipo en `LogisticsQueueService::JOB_TYPES`

3. Registrar el procesador en `logistics_worker.php`:

```php
$processors['mi_nuevo_trabajo'] = new MiNuevoProcessor();
```

4. Encolar trabajos:

```php
LogisticsQueueService::queue('mi_nuevo_trabajo', $pedidoId);
```

## Integración con APIs Externas

Los procesadores actuales tienen placeholders para integraciones reales:

- **GenerarGuiaProcessor**: Integrar con FedEx, DHL, UPS API
- **ActualizarTrackingProcessor**: Consultar APIs de tracking
- **ValidarDireccionProcessor**: Usar Google Maps Geocoding API
- **NotificarEstadoProcessor**: Integrar PHPMailer, SendGrid, Twilio SMS

Ver TODOs en los archivos de procesadores para puntos de integración.
