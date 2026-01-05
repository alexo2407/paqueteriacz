# Guía de Despliegue del Worker CLI en Producción (Panel Ferozo / CentOS 7)

Para que el CRM Relay procese mensajes (leads, webhooks) de manera automática, es necesario que el script `cli/crm_worker.php` se ejecute continuamente.

Aquí tienes dos opciones: **Cron (Fácil/Compatible)** y **Systemd (Avanzado/Recomendado)**.

---

## Opción 1: Tareas Programadas (Cron) - **Recomendado para Panel Ferozo**
Esta es la opción más sencilla si usas un panel de control. El worker se ejecutará cada minuto, procesará todo lo pendiente y se detendrá.

### Pasos en Panel Ferozo:
1. Ingresa a tu Panel Ferozo.
2. Busca la sección **Acciones Automáticas** o **Tareas Programadas (Cron)**.
3. Crea una nueva tarea con la siguiente configuración:
   - **Frecuencia**: Cada minuto (`* * * * *`)
   - **Comando**:
     ```bash
     /usr/bin/php /home/usuario/public_html/cli/crm_worker.php --once >> /home/usuario/public_html/logs/crm_worker.log 2>&1
     ```
   *(Asegúrate de ajustar la ruta `/home/usuario/public_html` a la ruta real de tu proyecto en el servidor)*.

### Cómo probarlo desde la terminal (SSH):
Ejecuta el comando manualmente para ver si funciona:
```bash
cd /ruta/a/tu/proyecto
php cli/crm_worker.php --once
```
Si no arroja errores y procesa rápido, está listo para el Cron.

---

## Opción 2: Servicio Systemd - **Recomendado para VPS (Root)**
Si tienes acceso root/sudo en tu servidor CentOS 7, lo mejor es instalarlo como un servicio del sistema. Esto permite que el worker corra en "bucle infinito" procesando en tiempo real (cada 3 segundos) y se reinicie automáticamente si falla.

### 1. Crear el archivo de servicio
Crea el archivo `/etc/systemd/system/crm-worker.service`:

```ini
[Unit]
Description=CRM Relay Worker (Procesa Leads y Webhooks)
After=network.target mariadb.service

[Service]
Type=simple
User=apache
Group=apache
# AJUSTA ESTA RUTA A TU CARPETA DE PROYECTO:
WorkingDirectory=/var/www/html/paqueteriacz
# AJUSTA LA RUTA DE PHP Y DEL ARCHIVO:
ExecStart=/usr/bin/php cli/crm_worker.php --loop
Restart=always
RestartSec=10
StandardOutput=append:/var/log/crm-worker.log
StandardError=append:/var/log/crm-worker-error.log

[Install]
WantedBy=multi-user.target
```

*> **Nota**: Reemplaza `User=apache` por el usuario dueño de los archivos (ej. el nombre de usuario de Ferozo) y ajusta las rutas.*

### 2. Activar el servicio
Ejecuta estos comandos en la terminal SSH:

```bash
# Recargar configuración
sudo systemctl daemon-reload

# Habilitar para que inicie al prender el servidor
sudo systemctl enable crm-worker

# Iniciar ahora
sudo systemctl start crm-worker

# Ver estado
sudo systemctl status crm-worker
```

---

## Verificando que funciona
1. Ve a **CRM > Monitor Worker** en tu sistema.
2. Deberías ver que el estado cambia a "Activo" o ver actualizaciones en los logs.
3. Si envías un Lead, debería procesarse automáticamente.
