# Sistema de Caché - Guía de Uso

## Descripción

Sistema de caché simple basado en archivos para mejorar el rendimiento de queries de catálogo.

## Archivos

- `utils/Cache.php` - Clase principal de caché
- `utils/CacheInvalidator.php` - Helper para invalidación
- `cache/` - Directorio de almacenamiento (auto-creado)

## Uso Básico

### Cachear Datos

```php
require_once __DIR__ . '/utils/Cache.php';

// Cachear por 1 hora (3600 segundos)
$estados = Cache::remember('estados_pedidos', 3600, function() {
    return PedidosModel::obtenerEstados();
});
```

### Invalidar Caché

```php
require_once __DIR__ . '/utils/CacheInvalidator.php';

// Cuando se crea/edita/elimina un producto
CacheInvalidator::invalidateProductos();

// Cuando se crea/edita un usuario vendedor
CacheInvalidator::invalidateVendedores();

// Invalidar todo (usar con cuidado)
CacheInvalidator::invalidateAll();
```

## TTL Configurados

| Dato | TTL | Razón |
|------|-----|-------|
| Estados | 24 horas | Nunca cambian |
| Vendedores | 1 hora | Cambian raramente |
| Productos | 10 minutos | Cambian más frecuentemente |
| Monedas | 2 horas | Cambian raramente |
| Proveedores | 1 hora | Cambian raramente |

## Métodos Disponibles

### Cache::remember($key, $ttl, $callback)
Obtiene del caché o ejecuta callback y cachea el resultado.

### Cache::get($key)
Obtiene valor del caché (null si no existe o expiró).

### Cache::set($key, $value, $ttl)
Guarda valor en caché.

### Cache::delete($key)
Elimina entrada específica.

### Cache::clear()
Limpia todo el caché.

### Cache::cleanup()
Elimina entradas expiradas.

### Cache::stats()
Obtiene estadísticas del caché.

## Ejemplo Completo

```php
// En ProductoController

require_once __DIR__ . '/../utils/CacheInvalidator.php';

class ProductoController {
    
    public function guardar($data) {
        // Guardar producto
        $result = ProductoModel::crear($data);
        
        // Invalidar caché de productos
        CacheInvalidator::invalidateProductos();
        
        return $result;
    }
    
    public function actualizar($id, $data) {
        $result = ProductoModel::actualizar($id, $data);
        CacheInvalidator::invalidateProductos();
        return $result;
    }
    
    public function eliminar($id) {
        $result = ProductoModel::eliminar($id);
        CacheInvalidator::invalidateProductos();
        return $result;
    }
}
```

## Mantenimiento

### Ver Estadísticas

```php
$stats = Cache::stats();
print_r($stats);
// Array (
//     [total_files] => 6
//     [valid_entries] => 5
//     [expired_entries] => 1
//     [total_size_bytes] => 15234
//     [total_size_kb] => 14.88
// )
```

### Limpiar Expirados

```php
$deleted = Cache::cleanup();
echo "Eliminados: $deleted archivos expirados";
```

### Limpiar Todo

```php
$deleted = Cache::clear();
echo "Eliminados: $deleted archivos";
```

## Notas Importantes

- El directorio `cache/` debe tener permisos de escritura (755)
- Los archivos .cache son ignorados por Git
- El caché es local al servidor (no compartido entre servidores)
- En caso de error, el sistema hace fallback a query directo
