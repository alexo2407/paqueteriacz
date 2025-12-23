-- Migración: Crear índices adicionales para optimización
-- Fecha: 2025-12-22
-- Descripción: Índices compuestos y adicionales para mejorar rendimiento de consultas frecuentes

-- Índices compuestos en tabla productos
CREATE INDEX idx_productos_categoria_activo ON productos(categoria_id, activo);
CREATE INDEX idx_productos_stock_activo ON productos(stock_minimo, activo);

-- Índices compuestos en tabla stock para reportes
CREATE INDEX idx_stock_producto_tipo_fecha ON stock(id_producto, tipo_movimiento, created_at);
CREATE INDEX idx_stock_fecha_tipo ON stock(created_at, tipo_movimiento);

-- Índices compuestos en tabla inventario
CREATE INDEX idx_inventario_producto_ubicacion ON inventario(id_producto, ubicacion);
CREATE INDEX idx_inventario_disponible_producto ON inventario(cantidad_disponible, id_producto);

-- Índices compuestos en tabla pedidos para filtros comunes
CREATE INDEX idx_pedidos_estado_fecha ON pedidos(id_estado, fecha_ingreso);
CREATE INDEX idx_pedidos_proveedor_estado ON pedidos(id_proveedor, id_estado);
CREATE INDEX idx_pedidos_vendedor_estado ON pedidos(id_vendedor, id_estado);
CREATE INDEX idx_pedidos_fecha_prioridad ON pedidos(fecha_ingreso, prioridad);

-- Índices en tabla pedidos_historial_estados
CREATE INDEX idx_historial_pedido_fecha ON pedidos_historial_estados(id_pedido, created_at);
CREATE INDEX idx_historial_estado_fecha ON pedidos_historial_estados(id_estado_nuevo, created_at);

-- Índice fulltext para búsqueda en productos (opcional, útil para búsquedas de texto)
-- Descomentar si se necesita búsqueda de texto completo
-- ALTER TABLE productos ADD FULLTEXT INDEX idx_productos_fulltext (nombre, descripcion);

-- Estadísticas de tablas para el optimizador
ANALYZE TABLE productos;
ANALYZE TABLE stock;
ANALYZE TABLE inventario;
ANALYZE TABLE pedidos;
ANALYZE TABLE pedidos_productos;
ANALYZE TABLE pedidos_historial_estados;
ANALYZE TABLE categorias_productos;
