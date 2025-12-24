# Generar SKUs Faltantes

Este documento explica cómo resolver el problema de productos sin SKU.

## Problema

Los productos creados antes de implementar el campo SKU no tienen este valor, lo que puede causar problemas en las vistas de edición.

## Soluciones Implementadas

### 1. Solución Individual (Vista de Edición)

**Archivo:** `vista/modulos/productos/editar.php`

**Funcionalidad:**
- Si un producto **NO tiene SKU**, aparecerá un botón **"Generar"** junto al campo SKU
- Al hacer clic, se genera automáticamente un SKU basado en la categoría seleccionada
- El usuario puede editar manualmente el SKU generado antes de guardar
- Se muestra un mensaje de advertencia indicando que el producto no tiene SKU

**Uso:**
1. Abre un producto sin SKU para editarlo
2. Selecciona una categoría (si no tiene)
3. Haz clic en el botón **"Generar"**
4. Se generará un SKU como: `ELEC-042` (CATEGORÍA-NÚMERO)
5. Guarda el producto

### 2. Solución Masiva (Script SQL)

**Archivo:** `migraciones/generar_skus_faltantes.sql`

**Funcionalidad:**
- Genera SKUs para **TODOS** los productos que no tienen uno
- Usa el formato: `[CATEGORÍA]-[ID]`
  - Ejemplo: `ELEC-001`, `FRUG-023`, `PROD-099`
- Para productos sin categoría usa el prefijo `PROD`

**Uso:**

#### Opción A: Desde phpMyAdmin

1. Abre phpMyAdmin: http://localhost/phpmyadmin
2. Selecciona tu base de datos `paqueteriacz`
3. Ve a la pestaña **SQL**
4. Copia y pega el contenido de `migraciones/generar_skus_faltantes.sql`
5. Haz clic en **Ejecutar**

#### Opción B: Desde línea de comandos

```bash
# Navega a la carpeta del proyecto
cd /Applications/XAMPP/xamppfiles/htdocs/paqueteriacz

# Ejecuta el script SQL
mysql -u root -p paqueteriacz < migraciones/generar_skus_faltantes.sql
```

#### Opción C: Usar el script PHP de ayuda

```bash
# Ejecutar el script PHP que aplica la migración
php migraciones/ejecutar_generar_skus.php
```

## Verificación

Después de aplicar la solución masiva, verifica:

```sql
-- Contar productos sin SKU (debería ser 0)
SELECT COUNT(*) as sin_sku 
FROM productos 
WHERE sku IS NULL OR sku = '';

-- Ver todos los SKUs generados
SELECT id, nombre, sku, categoria_id 
FROM productos 
ORDER BY sku;
```

## Recomendaciones

1. **Usa la solución masiva primero** si tienes muchos productos sin SKU
2. **Usa la solución individual** para casos específicos o nuevos productos
3. **Siempre verifica** que los SKUs sean únicos después de la generación
4. **Considera** establecer una convención de SKUs para tu empresa

## Formato de SKU

**Estructura:** `[PREFIJO]-[NÚMERO]`

- **PREFIJO:** Primeras 4 letras de la categoría (ej: ELEC, FRUT, BEBÍ)
- **NÚMERO:** Número de 3 dígitos basado en el ID del producto
- **Ejemplos:**
  - `ELEC-001` - Electrónica, producto ID 1
  - `ALIM-042` - Alimentos, producto ID 42
  - `PROD-099` - Sin categoría, producto ID 99

## Notas Importantes

- ✅ El campo SKU es **obligatorio** desde la implementación
- ✅ Los SKUs deben ser **únicos** en la base de datos
- ⚠️ Si modificas manualmente un SKU, asegúrate de que no exista otro igual
- 💡 Considera agregar una restricción UNIQUE al campo SKU en la base de datos
