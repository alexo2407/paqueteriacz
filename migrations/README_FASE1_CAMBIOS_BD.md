# Documentación de Cambios en Base de Datos - Fase 1

## Fecha: 2025-12-22
## Versión: 1.0

---

## Resumen de Cambios

Esta fase implementa mejoras fundamentales en el esquema de base de datos para mejorar la gestión de stock, productos y pedidos. Los cambios incluyen nuevas tablas, campos adicionales en tablas existentes, triggers automáticos e índices de optimización.

---

## Nuevas Tablas Creadas

### 1. `categorias_productos`
**Propósito:** Organizar productos en categorías jerárquicas

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | Primary Key |
| nombre | VARCHAR(100) | Nombre de la categoría |
| descripcion | TEXT | Descripción opcional |
| padre_id | INT | Categoría padre (para subcategorías) |
| activo | BOOLEAN | Estado de la categoría |
| created_at | TIMESTAMP | Fecha de creación |
| updated_at | TIMESTAMP | Fecha de actualización |

**Características:**
- Soporte para categorías anidadas
- Categorías iniciales pre-pobladas: Electrónica, Ropa, Alimentos, Hogar, Otros

---

### 2. `inventario`
**Propósito:** Tabla consolidada de inventario por producto y ubicación

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | Primary Key |
| id_producto | INT | Foreign Key a productos |
| ubicacion | VARCHAR(100) | Ubicación física |
| cantidad_disponible | INT | Stock disponible para venta |
| cantidad_reservada | INT | Stock reservado en pedidos pendientes |
| costo_promedio | DECIMAL(10,2) | Costo promedio ponderado |
| ultima_entrada | TIMESTAMP | Última entrada de stock |
| ultima_salida | TIMESTAMP | Última salida de stock |
| updated_at | TIMESTAMP | Última actualización |

**Características:**
- Se actualiza automáticamente mediante trigger
- Proporciona consultas rápidas de stock actual
- Elimina necesidad de sumar movimientos de tabla `stock`

---

### 3. `pedidos_historial_estados`
**Propósito:** Auditoría completa de cambios de estado en pedidos

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT | Primary Key |
| id_pedido | INT | Foreign Key a pedidos |
| id_estado_anterior | INT | Estado previo |
| id_estado_nuevo | INT | Nuevo estado |
| id_usuario | INT | Usuario que realizó el cambio |
| observaciones | TEXT | Notas del cambio |
| ip_address | VARCHAR(45) | IP del cambio |
| created_at | TIMESTAMP | Fecha del cambio |

**Características:**
- Trigger automático registra todos los cambios de estado
- Auditoría completa de quién y cuándo cambió el estado
- Pre-poblada con estado actual de pedidos existentes

---

## Tablas Modificadas

### 1. `productos`
**Nuevos campos agregados:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| sku | VARCHAR(100) | Código único del producto |
| categoria_id | INT | FK a categorias_productos |
| marca | VARCHAR(100) | Marca del producto |
| unidad_medida | ENUM | unidad, kg, litro, metro, caja, paquete |
| stock_minimo | INT | Nivel mínimo de stock (alerta) |
| stock_maximo | INT | Nivel máximo recomendado |
| activo | BOOLEAN | Estado del producto |
| imagen_url | VARCHAR(500) | URL de imagen del producto |
| created_at | TIMESTAMP | Fecha de creación |
| updated_at | TIMESTAMP | Fecha de actualización |

**Índices creados:**
- `idx_producto_categoria`
- `idx_producto_activo`
- `idx_producto_sku`
- `idx_producto_marca`
- `idx_productos_categoria_activo` (compuesto)
- `idx_productos_stock_activo` (compuesto)

---

### 2. `stock`
**Nuevos campos agregados:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| tipo_movimiento | ENUM | entrada, salida, ajuste, devolucion, transferencia |
| referencia_tipo | ENUM | pedido, compra, ajuste_manual, devolucion, transferencia |
| referencia_id | INT | ID del documento origen |
| motivo | VARCHAR(255) | Motivo del movimiento |
| ubicacion_origen | VARCHAR(100) | Ubicación de origen |
| ubicacion_destino | VARCHAR(100) | Ubicación de destino |
| costo_unitario | DECIMAL(10,2) | Costo al momento del movimiento |
| created_at | TIMESTAMP | Fecha del movimiento |

**Índices creados:**
- `idx_stock_tipo_movimiento`
- `idx_stock_referencia`
- `idx_stock_producto_fecha`
- `idx_stock_ubicacion_destino`
- `idx_stock_created_at`
- `idx_stock_producto_tipo_fecha` (compuesto)
- `idx_stock_fecha_tipo` (compuesto)

---

### 3. `pedidos_productos`
**Nuevos campos agregados:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| precio_unitario_usd | DECIMAL(10,2) | Precio al momento de la compra |
| descuento_porcentaje | DECIMAL(5,2) | Descuento aplicado |
| subtotal_usd | DECIMAL(10,2) | Subtotal calculado automáticamente |
| notas | TEXT | Notas del producto en el pedido |

**Características:**
- `subtotal_usd` es un campo calculado (GENERATED)
- Precios históricos preservan el valor al momento de la compra
- Constraint valida descuento entre 0 y 100%

---

### 4. `pedidos`
**Nuevos campos agregados:**

| Campo | Tipo | Descripción |
|-------|------|-------------|
| subtotal_usd | DECIMAL(10,2) | Suma de subtotales de productos |
| descuento_usd | DECIMAL(10,2) | Descuento total del pedido |
| impuestos_usd | DECIMAL(10,2) | Impuestos aplicados |
| total_usd | DECIMAL(10,2) | Total final calculado |
| fecha_estimada_entrega | DATE | Fecha estimada de entrega |
| prioridad | ENUM | baja, normal, alta, urgente |

**Características:**
- Función `calcular_subtotal_pedido()` suma automáticamente productos
- Triggers actualizan totales al modificar productos
- Índices para búsquedas por prioridad y fecha

**Índices creados:**
- `idx_pedidos_prioridad`
- `idx_pedidos_fecha_estimada`
- `idx_pedidos_total`
- `idx_pedidos_estado_fecha` (compuesto)
- `idx_pedidos_proveedor_estado` (compuesto)
- `idx_pedidos_vendedor_estado` (compuesto)
- `idx_pedidos_fecha_prioridad` (compuesto)

---

## Triggers Creados

### 1. `after_stock_insert`
**Tabla:** stock  
**Evento:** AFTER INSERT  
**Acción:** Actualiza tabla `inventario` automáticamente al registrar movimiento

### 2. `after_pedido_update_estado`
**Tabla:** pedidos  
**Evento:** AFTER UPDATE  
**Acción:** Registra cambio en `pedidos_historial_estados` cuando cambia el estado

### 3. `after_pedidos_productos_change` (INSERT/UPDATE/DELETE)
**Tabla:** pedidos_productos  
**Eventos:** AFTER INSERT, UPDATE, DELETE  
**Acción:** Recalcula totales del pedido automáticamente

---

## Funciones Creadas

### 1. `calcular_subtotal_pedido(pedido_id INT)`
**Retorna:** DECIMAL(10,2)  
**Propósito:** Calcula la suma de subtotales de todos los productos de un pedido

---

## Migración de Datos Existentes

### Productos
- Se asignó categoría "Otros" a productos existentes
- Se marcaron todos como `activo = TRUE`
- Se preservaron todos los datos existentes

### Stock
- Se determinó `tipo_movimiento` basado en cantidad (positivo = entrada, negativo = salida)
- Se asignó ubicación "Principal" a todos los movimientos
- Se preservó la tabla de movimientos completa

### Inventario
- Se calculó automáticamente sumando movimientos de stock
- Se creó registro consolidado por producto

### Pedidos
- Se calcularon subtotales basados en productos
- Se estableció prioridad "normal" para todos
- Se pre-pobló historial con estado actual

### Pedidos Productos
- Se asignaron precios basados en `productos.precio_usd` actual
- Se estableció descuento en 0 para registros existentes

---

## Mejoras de Rendimiento

### Índices Compuestos
Se crearon índices compuestos para las consultas más frecuentes:
- Productos por categoría y estado
- Stock por producto, tipo y fecha
- Pedidos por estado y fecha
- Pedidos por proveedor/vendedor y estado

### Campos Calculados
- `pedidos_productos.subtotal_usd`: Calculado automáticamente
- Elimina necesidad de cálculos en cada consulta

### Tabla Consolidada
- `inventario`: Proporciona stock actual sin sumar movimientos
- Reduce consultas de O(n) a O(1)

---

## Impacto en Código PHP

### Modelos que necesitan actualizarse:
1. ✅ `ProductoModel` - Agregar métodos para categorías, SKU, stock mínimo
2. ✅ `StockModel` - Usar nuevo esquema de movimientos con tipo y referencia
3. 🆕 `InventarioModel` - Crear nuevo modelo para tabla inventario
4. ✅ `PedidoModel` - Usar nuevos campos de totales y prioridad
5. 🆕 `CategoriaModel` - Crear nuevo modelo para categorías

### Controladores que necesitan actualizarse:
1. `ProductosController` - Gestión de categorías y nuevos filtros
2. `StockController` - Tipos de movimiento y trazabilidad
3. `PedidosController` - Manejo de totales y prioridad

### Vistas que necesitan actualizarse:
1. `productos/listar.php` - Mostrar categoría, stock mínimo/máximo
2. `productos/crear.php` - Campos adicionales
3. `stock/listar.php` - Filtros por tipo de movimiento
4. `pedidos/listar.php` - Filtros por prioridad, mostrar totales
5. `pedidos/crearPedido.php` - Descuentos e impuestos

---

## Orden de Aplicación de Migraciones

**IMPORTANTE:** Las migraciones deben aplicarse en este orden exacto:

1. `20251222_create_categorias_productos.sql`
2. `20251222_alter_productos_add_fields.sql`
3. `20251222_create_inventario_table.sql`
4. `20251222_alter_stock_add_fields.sql`
5. `20251222_create_pedidos_historial_estados.sql`
6. `20251222_alter_pedidos_productos.sql`
7. `20251222_alter_pedidos_add_totals.sql`
8. `20251222_create_indexes_optimization.sql`

**Script automatizado:** `apply_fase1_migrations.sh`

---

## Rollback (En caso de problemas)

Si es necesario revertir las migraciones:

1. Restaurar backup creado antes de migrar
2. O ejecutar los siguientes comandos en orden inverso:

```sql
-- Eliminar índices
DROP INDEX idx_productos_categoria_activo ON productos;
-- ... (resto de índices)

-- Eliminar triggers
DROP TRIGGER IF EXISTS after_stock_insert;
DROP TRIGGER IF EXISTS after_pedido_update_estado;
DROP TRIGGER IF EXISTS after_pedidos_productos_change;
-- ... (resto de triggers)

-- Eliminar funciones
DROP FUNCTION IF EXISTS calcular_subtotal_pedido;

-- Eliminar campos agregados
ALTER TABLE pedidos DROP COLUMN subtotal_usd;
-- ... (resto de campos)

-- Eliminar tablas nuevas
DROP TABLE IF EXISTS pedidos_historial_estados;
DROP TABLE IF EXISTS inventario;
DROP TABLE IF EXISTS categorias_productos;
```

---

## Verificación Post-Migración

Ejecutar las siguientes consultas para verificar:

```sql
-- Verificar que categorías se crearon
SELECT COUNT(*) FROM categorias_productos;

-- Verificar que productos tienen categoría
SELECT COUNT(*) FROM productos WHERE categoria_id IS NOT NULL;

-- Verificar que inventario está poblado
SELECT COUNT(*) FROM inventario;

-- Verificar triggers
SHOW TRIGGERS LIKE 'stock';
SHOW TRIGGERS LIKE 'pedidos';
SHOW TRIGGERS LIKE 'pedidos_productos';

-- Verificar índices
SHOW INDEX FROM productos;
SHOW INDEX FROM stock;
SHOW INDEX FROM pedidos;
```

---

## Próximos Pasos

1. ✅ Aplicar migraciones de base de datos (Fase 1 - Completada)
2. ⏭️ Actualizar modelos PHP (Fase 2)
3. ⏭️ Actualizar controladores (Fase 2)
4. ⏭️ Mejorar interfaces de usuario (Fase 3)
5. ⏭️ Implementar nuevas funcionalidades (Fase 4)

---

## Notas Importantes

- ⚠️ **Backup obligatorio** antes de aplicar migraciones
- ⚠️ Las migraciones son **irreversibles** sin backup
- ⚠️ Verificar que XAMPP/MySQL está corriendo
- ⚠️ Ejecutar migraciones en entorno de prueba primero
- ✅ Los triggers mantienen sincronización automática
- ✅ Los datos existentes se preservan
- ✅ Compatible con estructura actual

---

## Soporte y Contacto

Para dudas o problemas con las migraciones, consultar:
- `implementation_plan.md` - Plan completo de mejoras
- `task.md` - Estado de implementación
- Documentación de MySQL: https://dev.mysql.com/doc/
