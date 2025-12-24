# Guía de Implementación - Mejoras en Vistas (Fase 3A)

## ✅ Componentes Creados

### Helpers PHP
**Archivo:** `utils/ui_helpers.php`

Funciones disponibles:
- `badge_stock_nivel($actual, $minimo)` - Badge de estado de stock
- `badge_prioridad($prioridad)` - Badge de prioridad de pedido
- `badge_tipo_movimiento($tipo)` - Badge de tipo de movimiento
- `formatear_precio_usd($precio, $simbolo)` - Format precio
- `formatear_fecha($fecha, $incluirHora)` - Formato de fecha
- `icono_estado_activo($activo)` - Ícono activo/inactivo
- `generar_opciones_categorias($cats, $selected)` - Options para select
- `card_metrica($titulo, $valor, $icono, $color)` - Card de métrica
- `progress_bar_stock($actual, $min, $max)` - Barra de progreso

---

## 📋 Pasos para Mejorar las Vistas

### 1. Vista de Productos (`vista/modulos/productos/listar.php`)

#### Cambios a realizar:

**1.1 Incluir helpers al inicio:**
```php
<?php
require_once __DIR__ . '/../../../utils/ui_helpers.php';
require_once __DIR__ . '/../../../modelo/categoria.php';
```

**1.2 Obtener categorías para filtro:**
```php
$categorias = CategoriaModel::listarJerarquico();
$categoriaSeleccionada = $_GET['categoria'] ?? null;
```

**1.3 Agregar filtro de categorías antes de la tabla:**
```html
<div class="row mb-3">
    <div class="col-md-4">
        <label for="filtroCat">Filtrar por Categoría:</label>
        <select id="filtroCat" class="form-select">
            <?php echo generar_opciones_categorias($categorias, $categoriaSeleccionada); ?>
        </select>
    </div>
</div>
```

**1.4 Agregar columnas SKU y Marca a DataTable:**
```javascript
columns: [
    { data: 'id' },
    { data: 'sku' },  // NUEVO
    { data: 'nombre' },
    { data: 'marca' }, // NUEVO
    { data: 'precio_usd', render: function(data) {
        return formatear_precio_usd(data);
    }},
    { 
        data: 'stock_total',
        render: function(data, type, row) {
            // Usar helper para badge
            return data + ' ' + badge_stock_nivel(data, row.stock_minimo);
        }
    },
    { data: 'acciones' }
]
```

**1.5 Agregar JS para filtro de categorías:**
```javascript
$('#filtroCat').on('change', function() {
    const categoriaId = $(this).val();
    if (categoriaId) {
        window.location.href = '?categoria=' + categoriaId;
    } else {
        window.location.href = window.location.pathname;
    }
});
```

---

### 2. Vista de Stock (`vista/modulos/stock/listar.php`)

#### Cambios a realizar:

**2.1 Incluir helpers:**
```php
require_once __DIR__ . '/../../../utils/ui_helpers.php';
```

**2.2 Agregar filtros al inicio:**
```html
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label>Tipo de Movimiento:</label>
                <select id="filtroTipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="entrada">Entrada</option>
                    <option value="salida">Salida</option>
                    <option value="ajuste">Ajuste</option>
                    <option value="devolucion">Devolución</option>
                    <option value="transferencia">Transferencia</option>
                </select>
            </div>
            <div class="col-md-4">
                <label>Rango de Fechas:</label>
                <input type="text" id="rangoFechas" class="form-control" placeholder="Seleccionar rango">
            </div>
            <div class="col-md-2">
                <label>&nbsp;</label>
                <button id="btnFiltrar" class="btn btn-primary w-100">
                    <i class="bi bi-funnel"></i> Filtrar
                </button>
            </div>
        </div>
    </div>
</div>
```

**2.3 Agregar columna de tipo en DataTable:**
```javascript
{
    data: 'tipo_movimiento',
    render: function(data) {
        return badge_tipo_movimiento(data);
    }
}
```

**2.4 Implementar date range picker (requiere librería):**
```javascript
$('#rangoFechas').daterangepicker({
    locale: { format: 'DD/MM/YYYY' },
    autoUpdateInput: false
});
```

---

### 3. Vista de Pedidos (`vista/modulos/pedidos/listar.php`)

####Cambios a realizar:

**3.1 Incluir helpers:**
```php
require_once __DIR__ . '/../../../utils/ui_helpers.php';
```

**3.2 Agregar filtro de prioridad:**
```html
<div class="col-md-2">
    <label>Prioridad:</label>
    <select id="filtroPrioridad" class="form-select">
        <option value="">Todas</option>
        <option value="urgente">Urgente</option>
        <option value="alta">Alta</option>
        <option value="normal">Normal</option>
        <option value="baja">Baja</option>
    </select>
</div>
```

**3.3 Agregar columnas de totales y prioridad:**
```javascript
columns: [
    { data: 'numero_orden' },
    { data: 'destinatario' },
    { 
        data: 'prioridad',
        render: function(data) {
            return badge_prioridad(data);
        }
    },
    { 
        data: 'subtotal_usd',
        render: function(data) {
            return formatear_precio_usd(data);
        }
    },
    { 
        data: 'total_usd',
        render: function(data) {
            return formatear_precio_usd(data);
        }
    },
    { data: 'estado' },
    { data: 'acciones' }
]
```

---

### 4. Vista de Categorías (NUEVA)

**Crear:** `vista/modulos/categorias/listar.php`

```php
<?php
require_once __DIR__ . '/../../../utils/ui_helpers.php';
require_once __DIR__ . '/../../../modelo/categoria.php';
require_once __DIR__ . '/../../../controlador/categoria.php';

$controller = new CategoriaController();
$categorias = $controller->listarJerarquico();
$estadisticas = $controller->obtenerEstadisticas();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gestión de Categorías</title>
</head>
<body>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h5>Categorías de Productos</h5>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalNuevaCategoria">
                    <i class="bi bi-plus-circle"></i> Nueva Categoría
                </button>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Productos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $cat): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($cat['nombre']); ?></strong>
                            </td>
                            <td><?php echo $cat['total_productos'] ?? 0; ?></td>
                            <td><?php echo icono_estado_activo($cat['activo']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary">Editar</button>
                                <button class="btn btn-sm btn-danger">Eliminar</button>
                            </td>
                        </tr>
                        <?php if (!empty($cat['subcategorias'])): ?>
                            <?php foreach ($cat['subcategorias'] as $subcat): ?>
                            <tr>
                                <td class="ps-4">
                                    ↳ <?php echo htmlspecialchars($subcat['nombre']); ?>
                                </td>
                                <td><?php echo $subcat['total_productos'] ?? 0; ?></td>
                                <td><?php echo icono_estado_activo($subcat['activo']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary">Editar</button>
                                    <button class="btn btn-sm btn-danger">Eliminar</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
```

---

## 🔧 Dependencias Necesarias

### jQuery (ya debe estar instalado)
### Bootstrap 5 (ya debe estar instalado)
### Bootstrap Icons (agregar si no está):
```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
```

### Date Range Picker (opcional para filtros de fecha):
```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<script src="https://cdn.jsdelivr.net/npm/moment/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
```

---

## ✅ Checklist de Implementación

- [x] Crear `utils/ui_helpers.php`
- [ ] Mejorar `vista/modulos/productos/listar.php`
- [ ] Mejorar `vista/modulos/stock/listar.php`
- [ ] Mejorar `vista/modulos/pedidos/listar.php`
- [ ] Crear `vista/modulos/categorias/listar.php`
- [ ] Agregar ruta en `controlador/enlaces.php` para categorías
- [ ] Probar todas las vistas

---

## 📝 Notas Importantes

1. Todos los helpers generan HTML escapado para seguridad
2. Los badges usan clases de Bootstrap 5
3. Los íconos requieren Bootstrap Icons
4. Las funciones son reutilizables en todas las vistas
5. El código es retrocompatible - no rompe funcionalidad existente
