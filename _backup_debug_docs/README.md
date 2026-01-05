# 📦 Carpeta de Respaldo - Debug y Documentación

Esta carpeta contiene archivos de **debug** y **documentación auxiliar** que fueron movidos desde la raíz del proyecto para mantenerlo limpio y organizado.

## 📂 Contenido

### 📝 Archivos de Documentación (.md)
- `API_EJEMPLO_CREAR_PEDIDO.md` - Ejemplos de uso de API para crear pedidos
- `DEPLOY.md` - Notas sobre despliegue
- `DOCUMENTACION_FINAL.md` - Documentación general del proyecto
- `GUIA_IMPLEMENTACION_VISTAS.md` - Guía de implementación de vistas
- `RESUMEN_PROGRESO.md` - Resumen del progreso del proyecto
- `SOLUCION_RUTAS.md` - Soluciones relacionadas a rutas

### 🐛 Archivos de Debug (.php)
- `debug_roles.php` - Script de debug para roles de usuario
- `debug_table_structure.php` - Script para verificar estructura de tablas
- `verificar_leads.php` - Verificación de leads en el sistema
- `verify_delete_final.php` - Verificación de eliminación de datos
- `fix_crm_integrations.php` - Fix para integraciones CRM

### 🧪 Archivos de Testing
- `test_api.sh` - Script de pruebas de API
- `ejemplo_pedido.json` - Ejemplo de estructura de pedido

### 🗄️ Scripts SQL y Migraciones
- `asignar_rol_admin_user5.sql` - Script de asignación de roles
- `run_password_resets_migration.php` - Migración de resets de contraseña

### 📁 Carpetas
- **`docs/`** - 17 archivos de documentación (ejemplos API, guías, soluciones)
- **`migrations/`** - 31 archivos de migraciones de base de datos
- **`migraciones/`** - Migraciones adicionales
- **`postman/`** - Colecciones de Postman para testing de API
- **`sql/`** - Scripts SQL adicionales

## ⚠️ Importante

Estos archivos NO son necesarios para el funcionamiento del sistema en producción, pero pueden ser útiles para:
- Referencias futuras
- Debuggeo de problemas
- Recordar implementaciones pasadas
- Onboarding de nuevos desarrolladores

## 🗑️ ¿Puedo eliminar esta carpeta?

**SÍ**, pero se recomienda hacer un backup antes. Si no necesitas consultar esta información, puedes eliminarla sin afectar el funcionamiento del sistema.

---

*Carpeta creada el: 2026-01-04*  
*Razón: Limpieza del proyecto - mantener solo código productivo en raíz*
