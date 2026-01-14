#!/bin/bash
# Script de instalación automática de Workers en Producción
# Cruz Valle Logistics System

set -e  # Detener si hay errores

echo "=================================================="
echo "  Cruz Valle - Instalación de Workers"
echo "=================================================="
echo ""

# Colores
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Directorio base
PROJECT_DIR="/home/paquetes/public_html"
LOGS_DIR="$PROJECT_DIR/logs"

# Verificar que estamos en el directorio correcto
if [ ! -d "$PROJECT_DIR/cli" ]; then
    echo -e "${RED}Error: No se encuentra el directorio $PROJECT_DIR/cli${NC}"
    echo "Verifica la ruta del proyecto."
    exit 1
fi

echo -e "${GREEN}✓${NC} Directorio del proyecto encontrado: $PROJECT_DIR"

# Crear directorio de logs si no existe
if [ ! -d "$LOGS_DIR" ]; then
    mkdir -p "$LOGS_DIR"
    echo -e "${GREEN}✓${NC} Directorio de logs creado: $LOGS_DIR"
fi

# Dar permisos de escritura a logs
chmod 755 "$LOGS_DIR"
echo -e "${GREEN}✓${NC} Permisos configurados en directorio logs"

# Dar permisos de ejecución a los workers
chmod +x "$PROJECT_DIR/cli/logistics_worker.php"
chmod +x "$PROJECT_DIR/cli/crm_worker.php"
chmod +x "$PROJECT_DIR/cli/crm_bulk_worker.php"
chmod +x "$PROJECT_DIR/cli/crm_jobs_cleanup.php"
echo -e "${GREEN}✓${NC} Permisos de ejecución configurados en workers"

echo ""
echo "=================================================="
echo "  Creando archivos de servicio systemd..."
echo "=================================================="

# 1. Logistics Worker Service
cat > /tmp/logistics-worker.service << 'EOF'
[Unit]
Description=Logistics Worker - Address Validation Queue
After=network.target mysql.service

[Service]
Type=simple
User=paquetes
Group=paquetes
WorkingDirectory=/home/paquetes/public_html
ExecStart=/usr/bin/php /home/paquetes/public_html/cli/logistics_worker.php --loop
Restart=always
RestartSec=10
StandardOutput=append:/home/paquetes/public_html/logs/logistics-worker.log
StandardError=append:/home/paquetes/public_html/logs/logistics-worker-error.log

[Install]
WantedBy=multi-user.target
EOF

sudo cp /tmp/logistics-worker.service /etc/systemd/system/
echo -e "${GREEN}✓${NC} logistics-worker.service creado"

# 2. CRM Worker Service
cat > /tmp/crm-worker.service << 'EOF'
[Unit]
Description=CRM Worker - General CRM Tasks Processor
After=network.target mysql.service

[Service]
Type=simple
User=paquetes
Group=paquetes
WorkingDirectory=/home/paquetes/public_html
ExecStart=/usr/bin/php /home/paquetes/public_html/cli/crm_worker.php --loop
Restart=always
RestartSec=10
StandardOutput=append:/home/paquetes/public_html/logs/crm-worker.log
StandardError=append:/home/paquetes/public_html/logs/crm-worker-error.log

[Install]
WantedBy=multi-user.target
EOF

sudo cp /tmp/crm-worker.service /etc/systemd/system/
echo -e "${GREEN}✓${NC} crm-worker.service creado"

# 3. CRM Bulk Worker Service
cat > /tmp/crm-bulk-worker.service << 'EOF'
[Unit]
Description=CRM Bulk Worker - Mass Operations Processor
After=network.target mysql.service

[Service]
Type=simple
User=paquetes
Group=paquetes
WorkingDirectory=/home/paquetes/public_html
ExecStart=/usr/bin/php /home/paquetes/public_html/cli/crm_bulk_worker.php --loop
Restart=always
RestartSec=10
StandardOutput=append:/home/paquetes/public_html/logs/crm-bulk-worker.log
StandardError=append:/home/paquetes/public_html/logs/crm-bulk-worker-error.log

[Install]
WantedBy=multi-user.target
EOF

sudo cp /tmp/crm-bulk-worker.service /etc/systemd/system/
echo -e "${GREEN}✓${NC} crm-bulk-worker.service creado"

# 4. CRM Cleanup Service (oneshot)
cat > /tmp/crm-cleanup.service << 'EOF'
[Unit]
Description=CRM Cleanup - Remove Old CRM Jobs
After=network.target mysql.service

[Service]
Type=oneshot
User=paquetes
Group=paquetes
WorkingDirectory=/home/paquetes/public_html
ExecStart=/usr/bin/php /home/paquetes/public_html/cli/crm_jobs_cleanup.php
StandardOutput=append:/home/paquetes/public_html/logs/crm-cleanup.log
StandardError=append:/home/paquetes/public_html/logs/crm-cleanup-error.log
EOF

sudo cp /tmp/crm-cleanup.service /etc/systemd/system/
echo -e "${GREEN}✓${NC} crm-cleanup.service creado"

# 5. CRM Cleanup Timer (daily at 3 AM)
cat > /tmp/crm-cleanup.timer << 'EOF'
[Unit]
Description=Run CRM Cleanup Daily at 3 AM
Requires=crm-cleanup.service

[Timer]
OnCalendar=*-*-* 03:00:00
Persistent=true

[Install]
WantedBy=timers.target
EOF

sudo cp /tmp/crm-cleanup.timer /etc/systemd/system/
echo -e "${GREEN}✓${NC} crm-cleanup.timer creado"

# Limpiar archivos temporales
rm /tmp/*.service /tmp/*.timer 2>/dev/null || true

echo ""
echo "=================================================="
echo "  Activando servicios..."
echo "=================================================="

# Recargar systemd
sudo systemctl daemon-reload
echo -e "${GREEN}✓${NC} Systemd recargado"

# Habilitar servicios (arrancan al reiniciar el servidor)
sudo systemctl enable logistics-worker
sudo systemctl enable crm-worker
sudo systemctl enable crm-bulk-worker
sudo systemctl enable crm-cleanup.timer
echo -e "${GREEN}✓${NC} Servicios habilitados"

# Iniciar servicios
sudo systemctl start logistics-worker
sudo systemctl start crm-worker
sudo systemctl start crm-bulk-worker
sudo systemctl start crm-cleanup.timer
echo -e "${GREEN}✓${NC} Servicios iniciados"

echo ""
echo "=================================================="
echo "  Verificando estado..."
echo "=================================================="
echo ""

# Verificar estado de cada servicio
echo "📦 Logistics Worker:"
sudo systemctl is-active logistics-worker && echo -e "  ${GREEN}✓ Running${NC}" || echo -e "  ${RED}✗ Stopped${NC}"

echo "👥 CRM Worker:"
sudo systemctl is-active crm-worker && echo -e "  ${GREEN}✓ Running${NC}" || echo -e "  ${RED}✗ Stopped${NC}"

echo "📊 CRM Bulk Worker:"
sudo systemctl is-active crm-bulk-worker && echo -e "  ${GREEN}✓ Running${NC}" || echo -e "  ${RED}✗ Stopped${NC}"

echo "🧹 CRM Cleanup Timer:"
sudo systemctl is-active crm-cleanup.timer && echo -e "  ${GREEN}✓ Active${NC}" || echo -e "  ${RED}✗ Inactive${NC}"

echo ""
echo "=================================================="
echo "  ✅ Instalación completada exitosamente"
echo "=================================================="
echo ""
echo "Comandos útiles:"
echo ""
echo "  Ver estado de todos los workers:"
echo "    sudo systemctl status logistics-worker crm-worker crm-bulk-worker"
echo ""
echo "  Ver logs en tiempo real:"
echo "    tail -f $LOGS_DIR/logistics-worker.log"
echo "    tail -f $LOGS_DIR/crm-worker.log"
echo ""
echo "  Reiniciar todos los workers:"
echo "    sudo systemctl restart logistics-worker crm-worker crm-bulk-worker"
echo ""
echo "  Detener todos los workers:"
echo "    sudo systemctl stop logistics-worker crm-worker crm-bulk-worker"
echo ""
