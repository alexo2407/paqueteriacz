# Guía de Despliegue: Todos los Workers en Producción

## Workers del Sistema

El sistema tiene 4 workers que procesan tareas en segundo plano:

1. **logistics_worker.php** - Procesa validación de direcciones para pedidos
2. **crm_worker.php** - Procesa tareas del CRM (notificaciones, actualizaciones)
3. **crm_bulk_worker.php** - Procesa operaciones masivas del CRM
4. **crm_jobs_cleanup.php** - Limpia trabajos antiguos del sistema

---

## Opción 1: Cron Jobs (Hosting Compartido)

### Configurar Crontab

```bash
# Editar crontab
crontab -e
```

Agregar estas líneas:

```cron
# Logistics Worker - Procesa cola de validación de direcciones (cada minuto)
* * * * * /usr/bin/php /ruta/proyecto/cli/logistics_worker.php >> /ruta/proyecto/logs/logistics-worker.log 2>&1

# CRM Worker - Procesa tareas CRM generales (cada minuto)
* * * * * /usr/bin/php /ruta/proyecto/cli/crm_worker.php >> /ruta/proyecto/logs/crm-worker.log 2>&1

# CRM Bulk Worker - Procesa operaciones masivas (cada 2 minutos)
*/2 * * * * /usr/bin/php /ruta/proyecto/cli/crm_bulk_worker.php >> /ruta/proyecto/logs/crm-bulk-worker.log 2>&1

# CRM Cleanup - Limpia trabajos antiguos (cada día a las 3 AM)
0 3 * * * /usr/bin/php /ruta/proyecto/cli/crm_jobs_cleanup.php >> /ruta/proyecto/logs/crm-cleanup.log 2>&1
```

**Reemplazar `/ruta/proyecto/` con la ruta real en tu servidor.**

---

## Opción 2: Systemd Services (VPS/Dedicado - RECOMENDADO)

### Paso 1: Crear archivos de servicio

```bash
# Crear directorio para servicios (si no existe)
sudo mkdir -p /etc/systemd/system
```

### A. Logistics Worker Service

```bash
sudo nano /etc/systemd/system/logistics-worker.service
```

```ini
[Unit]
Description=Logistics Worker - Address Validation Queue
After=network.target mysql.service
PartOf=cruzvalle-workers.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/cruzvalle.website/public_html
ExecStart=/usr/bin/php /var/www/cruzvalle.website/public_html/cli/logistics_worker.php --loop
Restart=always
RestartSec=10
StandardOutput=append:/var/www/cruzvalle.website/public_html/logs/logistics-worker.log
StandardError=append:/var/www/cruzvalle.website/public_html/logs/logistics-worker-error.log

[Install]
WantedBy=multi-user.target
WantedBy=cruzvalle-workers.target
```

### B. CRM Worker Service

```bash
sudo nano /etc/systemd/system/crm-worker.service
```

```ini
[Unit]
Description=CRM Worker - General CRM Tasks Processor
After=network.target mysql.service
PartOf=cruzvalle-workers.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/cruzvalle.website/public_html
ExecStart=/usr/bin/php /var/www/cruzvalle.website/public_html/cli/crm_worker.php --loop
Restart=always
RestartSec=10
StandardOutput=append:/var/www/cruzvalle.website/public_html/logs/crm-worker.log
StandardError=append:/var/www/cruzvalle.website/public_html/logs/crm-worker-error.log

[Install]
WantedBy=multi-user.target
WantedBy=cruzvalle-workers.target
```

### C. CRM Bulk Worker Service

```bash
sudo nano /etc/systemd/system/crm-bulk-worker.service
```

```ini
[Unit]
Description=CRM Bulk Worker - Mass Operations Processor
After=network.target mysql.service
PartOf=cruzvalle-workers.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/cruzvalle.website/public_html
ExecStart=/usr/bin/php /var/www/cruzvalle.website/public_html/cli/crm_bulk_worker.php --loop
Restart=always
RestartSec=10
StandardOutput=append:/var/www/cruzvalle.website/public_html/logs/crm-bulk-worker.log
StandardError=append:/var/www/cruzvalle.website/public_html/logs/crm-bulk-worker-error.log

[Install]
WantedBy=multi-user.target
WantedBy=cruzvalle-workers.target
```

### D. CRM Cleanup Timer (se ejecuta 1 vez al día)

```bash
sudo nano /etc/systemd/system/crm-cleanup.service
```

```ini
[Unit]
Description=CRM Cleanup - Remove Old CRM Jobs
After=network.target mysql.service

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/var/www/cruzvalle.website/public_html
ExecStart=/usr/bin/php /var/www/cruzvalle.website/public_html/cli/crm_jobs_cleanup.php
StandardOutput=append:/var/www/cruzvalle.website/public_html/logs/crm-cleanup.log
StandardError=append:/var/www/cruzvalle.website/public_html/logs/crm-cleanup-error.log
```

```bash
sudo nano /etc/systemd/system/crm-cleanup.timer
```

```ini
[Unit]
Description=Run CRM Cleanup Daily at 3 AM
Requires=crm-cleanup.service

[Timer]
OnCalendar=daily
OnCalendar=*-*-* 03:00:00
Persistent=true

[Install]
WantedBy=timers.target
```

### E. Target para controlar todos los workers juntos

```bash
sudo nano /etc/systemd/system/cruzvalle-workers.target
```

```ini
[Unit]
Description=Cruz Valle Workers Target - Control all background workers
Requires=logistics-worker.service crm-worker.service crm-bulk-worker.service
After=network.target mysql.service

[Install]
WantedBy=multi-user.target
```

---

## Paso 2: Activar y arrancar los servicios

```bash
# Recargar systemd
sudo systemctl daemon-reload

# Habilitar servicios (arrancan automáticamente al reiniciar)
sudo systemctl enable logistics-worker
sudo systemctl enable crm-worker
sudo systemctl enable crm-bulk-worker
sudo systemctl enable crm-cleanup.timer

# Habilitar el target (agrupa todos los workers)
sudo systemctl enable cruzvalle-workers.target

# Iniciar todos los workers de una vez
sudo systemctl start cruzvalle-workers.target

# O iniciarlos individualmente
sudo systemctl start logistics-worker
sudo systemctl start crm-worker
sudo systemctl start crm-bulk-worker
sudo systemctl start crm-cleanup.timer

# Verificar estado de todos
sudo systemctl status logistics-worker
sudo systemctl status crm-worker
sudo systemctl status crm-bulk-worker
sudo systemctl status crm-cleanup.timer
```

---

## Comandos de Administración

### Ver estado de todos los workers:

```bash
# Estado resumido
systemctl list-units --type=service | grep -E '(logistics|crm)'

# Estado detallado del target
sudo systemctl status cruzvalle-workers.target

# Estado individual
sudo systemctl status logistics-worker
sudo systemctl status crm-worker
sudo systemctl status crm-bulk-worker
```

### Reiniciar todos los workers:

```bash
# Reiniciar todos de una vez
sudo systemctl restart cruzvalle-workers.target

# O individualmente
sudo systemctl restart logistics-worker
sudo systemctl restart crm-worker
sudo systemctl restart crm-bulk-worker
```

### Detener todos los workers:

```bash
sudo systemctl stop cruzvalle-workers.target
```

### Ver logs en tiempo real:

```bash
# Logistics Worker
sudo journalctl -u logistics-worker -f

# CRM Worker
sudo journalctl -u crm-worker -f

# CRM Bulk Worker
sudo journalctl -u crm-bulk-worker -f

# CRM Cleanup
sudo journalctl -u crm-cleanup -f

# Ver todos los workers juntos
sudo journalctl -f | grep -E '(logistics-worker|crm-worker|crm-bulk-worker|crm-cleanup)'
```

### Ver logs archivados:

```bash
# Últimas 100 líneas de cada worker
tail -n 100 logs/logistics-worker.log
tail -n 100 logs/crm-worker.log
tail -n 100 logs/crm-bulk-worker.log
tail -n 100 logs/crm-cleanup.log
```

---

## Verificación del Funcionamiento

### 1. Verificar que los procesos están corriendo:

```bash
ps aux | grep -E '(logistics_worker|crm_worker|crm_bulk_worker)' | grep -v grep
```

Deberías ver 3 procesos corriendo.

### 2. Verificar recursos utilizados:

```bash
# Ver uso de memoria y CPU
top -b -n 1 | grep -E '(logistics_worker|crm_worker|crm_bulk_worker)'
```

### 3. Verificar tablas de cola:

```sql
-- Conectar a MySQL
mysql -u usuario -p nombre_base_datos

-- Logistics Queue
SELECT status, COUNT(*) as count FROM logistics_queue GROUP BY status;

-- CRM Jobs Queue
SELECT job_type, status, COUNT(*) as count FROM crm_jobs GROUP BY job_type, status;
```

---

## Monitoreo Avanzado (Opcional)

### Script de monitoreo rápido:

```bash
# Crear script de monitoreo
sudo nano /usr/local/bin/check-workers.sh
```

```bash
#!/bin/bash
echo "=== Cruz Valle Workers Status ==="
echo ""

# Logistics Worker
echo "📦 Logistics Worker:"
systemctl is-active logistics-worker && echo "  ✓ Running" || echo "  ✗ Stopped"

# CRM Worker
echo "👥 CRM Worker:"
systemctl is-active crm-worker && echo "  ✓ Running" || echo "  ✗ Stopped"

# CRM Bulk Worker
echo "📊 CRM Bulk Worker:"
systemctl is-active crm-bulk-worker && echo "  ✓ Running" || echo "  ✗ Stopped"

echo ""
echo "=== Queue Stats ==="
mysql -u usuario -p'password' nombre_db -N -e "
  SELECT 'Logistics Queue:', status, COUNT(*) 
  FROM logistics_queue 
  GROUP BY status;
  
  SELECT 'CRM Jobs:', job_type, status, COUNT(*) 
  FROM crm_jobs 
  GROUP BY job_type, status;
"
```

```bash
# Dar permisos de ejecución
sudo chmod +x /usr/local/bin/check-workers.sh

# Ejecutar
check-workers.sh
```

---

## Troubleshooting

### Worker no inicia:

```bash
# Ver errores detallados
sudo journalctl -u logistics-worker -n 50 --no-pager
sudo journalctl -u crm-worker -n 50 --no-pager

# Verificar permisos
ls -la cli/*.php
# Deben ser ejecutables o tener permisos 644+

# Verificar sintaxis PHP
php -l cli/logistics_worker.php
php -l cli/crm_worker.php
php -l cli/crm_bulk_worker.php
```

### Worker se cae constantemente:

```bash
# Ver cuántas veces ha reiniciado
systemctl status logistics-worker | grep "Main PID"

# Aumentar RestartSec en el archivo .service si es necesario
sudo nano /etc/systemd/system/logistics-worker.service
# Cambiar: RestartSec=30 (en lugar de 10)

sudo systemctl daemon-reload
sudo systemctl restart logistics-worker
```

### Limpiar trabajos atascados:

```sql
-- Resetear trabajos en processing hace más de 15 minutos
UPDATE logistics_queue 
SET status = 'pending', updated_at = NOW()
WHERE status = 'processing' 
  AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE);

UPDATE crm_jobs 
SET status = 'pending', updated_at = NOW()
WHERE status = 'processing' 
  AND updated_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE);
```

---

## Recursos del Sistema

Uso aproximado de recursos por worker:

- **Logistics Worker**: ~8-15 MB RAM, <1% CPU
- **CRM Worker**: ~10-20 MB RAM, <2% CPU
- **CRM Bulk Worker**: ~15-30 MB RAM, <5% CPU (picos)
- **Total**: ~50-70 MB RAM, <10% CPU

Los workers son muy eficientes y no deberían impactar el rendimiento del servidor.

---

## Actualización de Workers

Cuando actualices el código:

```bash
# 1. Hacer git pull
cd /var/www/cruzvalle.website/public_html
git pull origin master

# 2. Reiniciar workers
sudo systemctl restart cruzvalle-workers.target

# 3. Verificar que están corriendo
sudo systemctl status cruzvalle-workers.target
```

---

## Respaldo Automático (Opcional)

Script para hacer backup de la configuración de workers:

```bash
sudo nano /usr/local/bin/backup-workers-config.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/root/workers-backup"
DATE=$(date +%Y%m%d-%H%M%S)

mkdir -p $BACKUP_DIR

# Backup de archivos systemd
cp /etc/systemd/system/logistics-worker.service $BACKUP_DIR/logistics-worker-$DATE.service
cp /etc/systemd/system/crm-worker.service $BACKUP_DIR/crm-worker-$DATE.service
cp /etc/systemd/system/crm-bulk-worker.service $BACKUP_DIR/crm-bulk-worker-$DATE.service
cp /etc/systemd/system/crm-cleanup.service $BACKUP_DIR/crm-cleanup-$DATE.service
cp /etc/systemd/system/crm-cleanup.timer $BACKUP_DIR/crm-cleanup-$DATE.timer

echo "Backup guardado en $BACKUP_DIR"
```

```bash
sudo chmod +x /usr/local/bin/backup-workers-config.sh
```
