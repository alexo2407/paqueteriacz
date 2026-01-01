# CRM Worker - Guía de Uso

## Descripción

Worker CLI para procesar las colas inbox y outbox del módulo CRM Relay.
Procesa mensajes de forma asíncrona cada 3 segundos.

## Modos de Ejecución

### Modo `--once` (Para Cron)
Procesa las colas una sola vez y termina. Ideal para ejecutar via cron.

```bash
php cli/crm_worker.php --once
```

### Modo `--loop` (Para Daemon)
Loop infinito que procesa cada 3 segundos. Ideal para systemd/supervisor.

```bash
php cli/crm_worker.php --loop
```

## Configuración con Cron

Ejecutar cada minuto:

```bash
* * * * * cd /xampp/htdocs/paqueteriacz && php cli/crm_worker.php --once >> logs/crm_worker.log 2>&1
```

## Configuración con Systemd (Recomendado para Producción)

Crear archivo `/etc/systemd/system/crm-worker.service`:

```ini
[Unit]
Description=CRM Relay Worker
After=mariadb.service
Requires=mariadb.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/xampp/htdocs/paqueteriacz
ExecStart=/usr/bin/php /xampp/htdocs/paqueteriacz/cli/crm_worker.php --loop
Restart=always
RestartSec=5
StandardOutput=append:/var/log/crm-worker.log
StandardError=append:/var/log/crm-worker-error.log

[Install]
WantedBy=multi-user.target
```

Habilitar y arrancar:

```bash
sudo systemctl enable crm-worker
sudo systemctl start crm-worker
sudo systemctl status crm-worker
```

Ver logs:

```bash
sudo journalctl -u crm-worker -f
```

## Configuración con Supervisor

Crear `/etc/supervisor/conf.d/crm-worker.conf`:

```ini
[program:crm-worker]
command=/usr/bin/php /xampp/htdocs/paqueteriacz/cli/crm_worker.php --loop
directory=/xampp/htdocs/paqueteriacz
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/crm-worker.log
```

Recargar supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status crm-worker
```

## Monitoreo

El worker escribe logs en:
- **Stdout**: Procesamiento normal (processed/sent counts)
- **error_log**: Errores via `error_log()` de PHP

Para monitorear en tiempo real:

```bash
# Con systemd
sudo journalctl -u crm-worker -f

# Con supervisor
sudo tail -f /var/log/crm-worker.log

# Con cron
tail -f logs/crm_worker.log
```

## Detención

### Modo --loop con systemd:
```bash
sudo systemctl stop crm-worker
```

### Modo --loop con supervisor:
```bash
sudo supervisorctl stop crm-worker
```

### Modo --loop manual:
Presionar `Ctrl+C` o enviar señal:
```bash
kill -SIGTERM <PID>
```

### Modo --once:
No requiere detención, termina automáticamente.

## Troubleshooting

### Worker no procesa mensajes
1. Verificar que MariaDB esté corriendo
2. Verificar credenciales en `config/config.php`
3. Verificar que tablas CRM existan
4. Revisar permisos del usuario que ejecuta el worker

### Mensajes atascados en outbox
1. Verificar que destino tenga integración activa en `crm_integrations`
2. Verificar conectividad HTTP al webhook_url
3. Revisar `last_error` en tabla `crm_outbox`

### CPU/Memoria alta
1. Reducir `$pollInterval` en el código (línea 21)
2. Limitar cantidad de mensajes procesados por iteración (parámetro `$limit` en services)

## Mantenimiento

### Limpiar mensajes antiguos
```sql
-- Borrar inbox procesados > 30 días
DELETE FROM crm_inbox WHERE status='processed' AND processed_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Borrar outbox enviados > 30 días
DELETE FROM crm_outbox WHERE status='sent' AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Ver estadísticas
```bash
curl -H "Authorization: Bearer $ADMIN_TOKEN" http://localhost/paqueteriacz/api/crm/metrics
```
