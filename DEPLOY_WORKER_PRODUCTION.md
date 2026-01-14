# Guía de Despliegue: Logistics Worker en Producción

## Requisitos Previos

1. Acceso SSH al servidor de producción
2. PHP CLI instalado (versión 7.4+)
3. Base de datos con la tabla `logistics_queue` creada

---

## Paso 1: Verificar archivos en producción

```bash
# Conectar por SSH al servidor
ssh usuario@cruzvalle.website

# Ir al directorio del proyecto
cd /ruta/del/proyecto

# Hacer git pull para obtener últimos cambios
git pull origin master

# Verificar que el worker existe
ls -la cli/logistics_worker.php
```

---

## Paso 2: Verificar la base de datos

```bash
# Comprobar que la tabla existe
mysql -u usuario -p nombre_base_datos -e "SHOW TABLES LIKE 'logistics_queue';"
```

Si la tabla NO existe, ejecutar la migración:

```bash
mysql -u usuario -p nombre_base_datos < database/migrations/create_logistics_queue.sql
```

---

## Paso 3: Probar el worker manualmente

```bash
# Navegar al directorio raíz del proyecto
cd /ruta/del/proyecto

# Ejecutar el worker una vez (modo test)
php cli/logistics_worker.php

# Si funciona sin errores, probar en modo loop por 30 segundos
timeout 30 php cli/logistics_worker.php --loop
```

**Errores comunes:**
- `Fatal error: Class not found` → Ejecutar: `composer install`
- `Permission denied` → Ejecutar: `chmod +x cli/logistics_worker.php`
- `Database connection failed` → Verificar credenciales en `config/config.php`

---

## Paso 4: Configurar Cron Job (Opción 1 - Recomendada para hosting compartido)

```bash
# Editar crontab
crontab -e

# Agregar la siguiente línea (ejecuta cada minuto)
* * * * * /usr/bin/php /ruta/completa/del/proyecto/cli/logistics_worker.php >> /ruta/completa/del/proyecto/logs/worker.log 2>&1
```

**Ventajas:**
- Simple de configurar
- Compatible con hosting compartido
- No requiere systemd ni supervisor

**Desventajas:**
- Se ejecuta cada minuto, no continuamente
- Puede haber pequeños retrasos de hasta 60 segundos

---

## Paso 5: Configurar Systemd Service (Opción 2 - Recomendada para VPS/Dedicado)

Si tienes acceso root o sudo, puedes configurar un servicio systemd:

```bash
# Crear el archivo de servicio
sudo nano /etc/systemd/system/logistics-worker.service
```

Contenido del archivo:

```ini
[Unit]
Description=Logistics Worker - Address Validation Queue Processor
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/ruta/completa/del/proyecto
ExecStart=/usr/bin/php /ruta/completa/del/proyecto/cli/logistics_worker.php --loop
Restart=always
RestartSec=5
StandardOutput=append:/ruta/completa/del/proyecto/logs/worker.log
StandardError=append:/ruta/completa/del/proyecto/logs/worker-error.log

[Install]
WantedBy=multi-user.target
```

Luego:

```bash
# Recargar systemd
sudo systemctl daemon-reload

# Habilitar el servicio (arranca automáticamente al reiniciar)
sudo systemctl enable logistics-worker

# Iniciar el servicio
sudo systemctl start logistics-worker

# Verificar estado
sudo systemctl status logistics-worker

# Ver logs en tiempo real
sudo journalctl -u logistics-worker -f
```

**Comandos útiles:**
```bash
# Detener el worker
sudo systemctl stop logistics-worker

# Reiniciar el worker
sudo systemctl restart logistics-worker

# Ver últimas 50 líneas de log
sudo journalctl -u logistics-worker -n 50
```

---

## Paso 6: Verificar funcionamiento

### Verificar que está procesando trabajos:

```bash
# Ver logs del worker
tail -f /ruta/completa/del/proyecto/logs/worker.log
```

### Crear un trabajo de prueba:

```bash
# Desde tu navegador o Postman, crear un pedido masivo con auto_enqueue=true
curl -X POST "https://cruzvalle.website/api/pedidos/multiple?auto_enqueue=true" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TU_TOKEN_AQUI" \
  -d '{
    "pedidos": [{
      "numero_orden": 999999,
      "destinatario": "Test Worker",
      "telefono": "12345678",
      "productos": [{"producto_id": 1, "cantidad": 1}],
      "latitud": 12.136389,
      "longitud": -86.251389,
      "direccion": "Managua, Nicaragua",
      "id_pais": 1,
      "id_departamento": 1
    }]
  }'
```

### Verificar que se procesó:

```sql
-- Conectar a MySQL
mysql -u usuario -p nombre_base_datos

-- Ver trabajos pendientes
SELECT * FROM logistics_queue WHERE status = 'pending' ORDER BY created_at DESC LIMIT 10;

-- Ver trabajos procesados recientemente
SELECT * FROM logistics_queue WHERE status = 'completed' ORDER BY updated_at DESC LIMIT 10;

-- Ver trabajos fallidos
SELECT * FROM logistics_queue WHERE status = 'failed' ORDER BY updated_at DESC LIMIT 10;
```

---

## Monitoreo y Mantenimiento

### Ver estadísticas del worker:

```bash
# Trabajos por estado
mysql -u usuario -p nombre_base_datos -e "
SELECT status, COUNT(*) as count 
FROM logistics_queue 
GROUP BY status;"
```

### Limpiar trabajos completados antiguos (opcional):

```sql
-- Eliminar trabajos completados hace más de 30 días
DELETE FROM logistics_queue 
WHERE status = 'completed' 
  AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Reintentar trabajos fallidos:

```sql
-- Reintentar trabajos que fallaron por primera vez
UPDATE logistics_queue 
SET status = 'pending', 
    attempts = 0, 
    error = NULL,
    updated_at = NOW()
WHERE status = 'failed' 
  AND attempts < 3;
```

---

## Troubleshooting

### Worker no procesa trabajos:

1. Verificar que está corriendo: `ps aux | grep logistics_worker`
2. Ver logs: `tail -f logs/worker.log`
3. Verificar conexión a BD: `php -r "require 'config/config.php'; echo 'OK';"`

### Trabajos se quedan en "processing":

```sql
-- Resetear trabajos atascados (más de 10 minutos procesando)
UPDATE logistics_queue 
SET status = 'pending', 
    updated_at = NOW()
WHERE status = 'processing' 
  AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE);
```

### Demasiados trabajos fallidos:

1. Revisar el error más común:
```sql
SELECT error, COUNT(*) as count 
FROM logistics_queue 
WHERE status = 'failed' 
GROUP BY error 
ORDER BY count DESC;
```

2. Verificar API de validación (si usas alguna externa)
3. Aumentar timeout en `cli/logistics_worker.php` si es necesario

---

## Notas Importantes

- **Logs**: Asegúrate de que el directorio `logs/` tenga permisos de escritura (775)
- **Recursos**: El worker usa muy pocos recursos (< 10 MB RAM típicamente)
- **Escalabilidad**: Puedes correr múltiples instancias del worker si tienes mucho volumen
- **Backups**: La tabla `logistics_queue` no necesita backup constante (es transitoria)

---

## Contacto y Soporte

Si el worker falla constantemente, revisa:
1. Logs del servidor: `/var/log/apache2/error.log` o `/var/log/nginx/error.log`
2. Logs de PHP: `php.ini` → `error_log` setting
3. Logs del worker: `logs/worker.log` y `logs/worker-error.log`
