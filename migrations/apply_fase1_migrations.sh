#!/bin/bash
# Script de aplicación de migraciones - Fase 1
# Fecha: 2025-12-22
# Descripción: Aplica todas las migraciones de mejoras de base de datos en orden

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuración de base de datos
DB_NAME="sistema_multinacional"
DB_USER="root"
DB_PASS=""

echo -e "${GREEN}╔════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  Aplicación de Migraciones - Fase 1               ║${NC}"
echo -e "${GREEN}║  Mejoras en Base de Datos                         ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════╝${NC}"
echo ""

# Función para ejecutar una migración
ejecutar_migracion() {
    local archivo=$1
    local descripcion=$2
    
    echo -e "${YELLOW}➤ Aplicando: ${descripcion}${NC}"
    echo -e "  Archivo: ${archivo}"
    
    if mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$archivo" 2>&1; then
        echo -e "${GREEN}  ✓ Completado exitosamente${NC}"
        echo ""
        return 0
    else
        echo -e "${RED}  ✗ Error al aplicar migración${NC}"
        echo ""
        return 1
    fi
}

# Función para crear backup antes de ejecutar
crear_backup() {
    local backup_file="backup_pre_migracion_$(date +%Y%m%d_%H%M%S).sql"
    
    echo -e "${YELLOW}Creando backup de la base de datos...${NC}"
    
    if mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$backup_file" 2>&1; then
        echo -e "${GREEN}✓ Backup creado: ${backup_file}${NC}"
        echo ""
        return 0
    else
        echo -e "${RED}✗ Error al crear backup${NC}"
        echo ""
        return 1
    fi
}

# Preguntar si desea crear backup
read -p "¿Desea crear un backup antes de aplicar las migraciones? (s/n): " respuesta
if [[ $respuesta == "s" || $respuesta == "S" ]]; then
    crear_backup
    if [ $? -ne 0 ]; then
        echo -e "${RED}No se pudo crear el backup. Abortando.${NC}"
        exit 1
    fi
fi

# Contador de migraciones
TOTAL=8
EXITOSAS=0
FALLIDAS=0

# Lista de migraciones en orden de ejecución
echo -e "${GREEN}Iniciando aplicación de ${TOTAL} migraciones...${NC}"
echo ""

# 1. Crear tabla categorías
if ejecutar_migracion \
    "20251222_create_categorias_productos.sql" \
    "1/${TOTAL} - Crear tabla categorias_productos"; then
    ((EXITOSAS++))
else
    ((FALLIDAS++))
fi

# 2. Alterar tabla productos
if ejecutar_migracion \
    "20251222_alter_productos_add_fields.sql" \
    "2/${TOTAL} - Ampliar tabla productos con nuevos campos"; then
    ((EXITOSAS++))
else
    ((FALLIDAS++))
fi

# 3. Crear tabla inventario
if ejecutar_migracion \
    "20251222_create_inventario_table.sql" \
    "3/${TOTAL} - Crear tabla inventario consolidado"; then
    ((EXITOSAS++))
else
    ((FALLIDAS++))
fi

# 4. Alterar tabla stock
if ejecutar_migracion \
    "20251222_alter_stock_add_fields.sql" \
    "4/${TOTAL} - Mejorar tabla stock con trazabilidad"; then
    ((EXITOSAS++))
else
    ((FALLIDAS++))
fi

# 5. Crear tabla historial estados
if ejecutar_migracion \
    "20251222_create_pedidos_historial_estados.sql" \
    "5/${TOTAL} - Crear tabla pedidos_historial_estados"; then
    ((EXITOSAS++))
else
    ((FALLIDAS++))
fi

# 6. Alterar tabla pedidos_productos
if ejecutar_migracion \
    "20251222_alter_pedidos_productos.sql" \
    "6/${TOTAL} - Mejorar tabla pedidos_productos con precios"; then
    ((EXITOSAS++))
else
    ((FALLIDAS++))
fi

# 7. Alterar tabla pedidos
if ejecutar_migracion \
    "20251222_alter_pedidos_add_totals.sql" \
    "7/${TOTAL} - Ampliar tabla pedidos con totales y prioridad"; then
    ((EXITOSAS++))
else
    ((FALLIDAS++))
fi

# 8. Crear índices de optimización
if ejecutar_migracion \
    "20251222_create_indexes_optimization.sql" \
    "8/${TOTAL} - Crear índices para optimización"; then
    ((EXITOSAS++))
else
    ((FALLIDAS++))
fi

# Resumen final
echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              RESUMEN DE MIGRACIONES                ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════╝${NC}"
echo -e "Total de migraciones: ${TOTAL}"
echo -e "${GREEN}Exitosas: ${EXITOSAS}${NC}"
if [ $FALLIDAS -gt 0 ]; then
    echo -e "${RED}Fallidas: ${FALLIDAS}${NC}"
fi
echo ""

if [ $FALLIDAS -eq 0 ]; then
    echo -e "${GREEN}✓ Todas las migraciones se aplicaron correctamente${NC}"
    echo -e "${YELLOW}⚠ Recuerda actualizar los modelos PHP para usar las nuevas tablas y campos${NC}"
    exit 0
else
    echo -e "${RED}✗ Algunas migraciones fallaron. Revisa los errores.${NC}"
    exit 1
fi
