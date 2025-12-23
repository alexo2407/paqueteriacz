<?php

include_once __DIR__ . '/conexion.php';

/**
 * ProductoModel
 *
 * Modelo encargado de las operaciones CRUD sobre la tabla `productos` y
 * consultas relacionadas con inventario (stock). Todos los métodos usan
 * la clase `Conexion` para obtener una instancia PDO.
 */
class ProductoModel
{
    public static function listarConInventario()
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = 'SELECT 
                        p.id,
                        p.sku,
                        p.nombre,
                        p.descripcion,
                        p.precio_usd,
                        p.categoria_id,
                        p.marca,
                        p.unidad_medida,
                        p.stock_minimo,
                        p.stock_maximo,
                        p.activo,
                        p.imagen_url,
                        COALESCE(SUM(s.cantidad), 0) AS stock_total
                    FROM productos p
                    LEFT JOIN stock s ON s.id_producto = p.id
                    GROUP BY p.id, p.sku, p.nombre, p.descripcion, p.precio_usd, 
                             p.categoria_id, p.marca, p.unidad_medida, p.stock_minimo, 
                             p.stock_maximo, p.activo, p.imagen_url
                    ORDER BY p.nombre ASC';
            $stmt = $db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar productos: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('
                SELECT 
                    p.id, 
                    p.nombre, 
                    p.sku,
                    p.descripcion, 
                    p.precio_usd,
                    p.categoria_id,
                    p.marca,
                    p.unidad_medida as unidad,
                    p.stock_minimo,
                    p.stock_maximo,
                    p.activo,
                    p.imagen_url,
                    p.created_at,
                    p.updated_at,
                    COALESCE(SUM(s.cantidad), 0) AS stock_total
                FROM productos p
                LEFT JOIN stock s ON s.id_producto = p.id
                WHERE p.id = :id
                GROUP BY p.id
            ');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Listar movimientos/entradas de stock para un producto
     * Devuelve un array de filas con: id, id_producto, id_usuario, cantidad, updated_at
     * @param int $id
     * @return array
     */
    public static function listarStockPorProducto($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, id_producto, id_usuario, cantidad, updated_at FROM stock WHERE id_producto = :id ORDER BY updated_at DESC');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar stock por producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Agregar un movimiento de stock para un producto.
     * Inserta una fila en la tabla `stock` con id_producto, id_usuario y cantidad.
     * Retorna el id insertado o null en error.
     * @param int $idProducto
     * @param int $idUsuario
     * @param int $cantidad
     * @return int|null
     */
    public static function agregarMovimientoStock($idProducto, $idUsuario, $cantidad)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO stock (id_producto, id_usuario, cantidad) VALUES (:id_producto, :id_usuario, :cantidad)');
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error al agregar movimiento de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Obtener stock total disponible para un producto (suma de stock.cantidad)
     * Devuelve null si ocurre un error o no existe el producto
     */
    public static function obtenerStockTotal($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT COALESCE(SUM(cantidad), 0) as stock_total FROM stock WHERE id_producto = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) return null;
            return isset($row['stock_total']) ? (int)$row['stock_total'] : 0;
        } catch (PDOException $e) {
            error_log('Error al obtener stock del producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function buscarPorNombre($nombre)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, nombre, descripcion, precio_usd FROM productos WHERE nombre = :nombre');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al buscar producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function crearRapido($nombre, $descripcion = null, $precioUsd = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO productos (nombre, descripcion, precio_usd) VALUES (:nombre, :descripcion, :precio_usd)');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            if ($descripcion === null || $descripcion === '') {
                $stmt->bindValue(':descripcion', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            }
            if ($precioUsd === null || $precioUsd === '') {
                $stmt->bindValue(':precio_usd', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':precio_usd', $precioUsd);
            }
            $stmt->execute();
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error al crear producto rápido: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Crear un producto con campos completos
     * @param string $nombre
     * @param string|null $descripcion
     * @param float|null $precioUsd
     * @return int|null ID creado o null en error
     */
    public static function crear($nombre, $descripcion = null, $precioUsd = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO productos (nombre, descripcion, precio_usd) VALUES (:nombre, :descripcion, :precio_usd)');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            if ($descripcion === null || $descripcion === '') {
                $stmt->bindValue(':descripcion', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':descripcion', $descripcion, PDO::PARAM_STR);
            }
            if ($precioUsd === null || $precioUsd === '') {
                $stmt->bindValue(':precio_usd', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':precio_usd', $precioUsd);
            }
            $stmt->execute();
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error al crear producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Actualizar un producto existente con todos los campos
     * @param int $id
     * @param array $datos Array asociativo con los campos a actualizar
     * @return bool True si se actualizó, False si no o en error
     */
    public static function actualizar($id, $datos)
    {
        try {
            $db = (new Conexion())->conectar();
            
            // Construir query dinámicamente solo con los campos proporcionados
            $campos = [];
            $valores = [':id' => $id];
            
            // Mapeo de campos permitidos
            $camposPermitidos = [
                'nombre' => PDO::PARAM_STR,
                'sku' => PDO::PARAM_STR,
                'descripcion' => PDO::PARAM_STR,
                'precio_usd' => PDO::PARAM_STR,
                'categoria_id' => PDO::PARAM_INT,
                'marca' => PDO::PARAM_STR,
                'unidad' => PDO::PARAM_STR,
                'stock_minimo' => PDO::PARAM_INT,
                'stock_maximo' => PDO::PARAM_INT,
                'activo' => PDO::PARAM_INT,
                'imagen_url' => PDO::PARAM_STR
            ];
            
            foreach ($camposPermitidos as $campo => $tipo) {
                if (array_key_exists($campo, $datos)) {
                    $valor = $datos[$campo];
                    
                    // Mapear 'unidad' a 'unidad_medida' para la BD
                    $campoBD = ($campo === 'unidad') ? 'unidad_medida' : $campo;
                    
                    // Manejar valores nulos/vacíos
                    if ($valor === null || $valor === '') {
                        $campos[] = "$campoBD = :$campo";
                        $valores[":$campo"] = null;
                    } else {
                        $campos[] = "$campoBD = :$campo";
                        $valores[":$campo"] = $valor;
                    }
                }
            }
            
            if (empty($campos)) {
                return false; // No hay nada que actualizar
            }
            
            $sql = 'UPDATE productos SET ' . implode(', ', $campos) . ' WHERE id = :id';
            $stmt = $db->prepare($sql);
            
            // Bind de todos los valores
            foreach ($valores as $key => $valor) {
                if ($valor === null) {
                    $stmt->bindValue($key, null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($key, $valor);
                }
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al actualizar producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Eliminar un producto por su ID
     * @param int $id
     * @return bool True si se eliminó, False en error
     */
    public static function eliminar($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('DELETE FROM productos WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al eliminar producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Listar productos por categoría
     * 
     * @param int $categoriaId ID de la categoría
     * @param bool $incluirInactivos Si incluir productos inactivos
     * @return array Lista de productos
     */
    public static function listarPorCategoria($categoriaId, $incluirInactivos = false)
    {
        try {
            $db = (new Conexion())->conectar();
            $whereActivo = $incluirInactivos ? '' : 'AND p.activo = TRUE';
            
            $sql = "SELECT 
                        p.id,
                        p.sku,
                        p.nombre,
                        p.descripcion,
                        p.precio_usd,
                        p.categoria_id,
                        p.marca,
                        p.stock_minimo,
                        p.stock_maximo,
                        p.activo,
                        COALESCE(SUM(s.cantidad), 0) AS stock_total
                    FROM productos p
                    LEFT JOIN stock s ON s.id_producto = p.id
                    WHERE p.categoria_id = :categoria_id {$whereActivo}
                    GROUP BY p.id
                    ORDER BY p.nombre ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':categoria_id', $categoriaId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar productos por categoría: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Buscar productos por múltiples criterios
     * 
     * @param array $criterios Array con filtros (nombre, sku, categoria_id, marca, activo)
     * @return array Lista de productos que coinciden
     */
    public static function buscarAvanzado($criterios = [])
    {
        try {
            $db = (new Conexion())->conectar();
            $where = [];
            $params = [];
            
            if (!empty($criterios['nombre'])) {
                $where[] = 'p.nombre LIKE :nombre';
                $params[':nombre'] = '%' . $criterios['nombre'] . '%';
            }
            
            if (!empty($criterios['sku'])) {
                $where[] = 'p.sku LIKE :sku';
                $params[':sku'] = '%' . $criterios['sku'] . '%';
            }
            
            if (!empty($criterios['categoria_id'])) {
                $where[] = 'p.categoria_id = :categoria_id';
                $params[':categoria_id'] = $criterios['categoria_id'];
            }
            
            if (!empty($criterios['marca'])) {
                $where[] = 'p.marca LIKE :marca';
                $params[':marca'] = '%' . $criterios['marca'] . '%';
            }
            
            if (isset($criterios['activo'])) {
                $where[] = 'p.activo = :activo';
                $params[':activo'] = $criterios['activo'] ? 1 : 0;
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $sql = "SELECT 
                        p.id,
                        p.sku,
                        p.nombre,
                        p.descripcion,
                        p.precio_usd,
                        p.categoria_id,
                        p.marca,
                        p.stock_minimo,
                        p.stock_maximo,
                        p.activo,
                        COALESCE(SUM(s.cantidad), 0) AS stock_total
                    FROM productos p
                    LEFT JOIN stock s ON s.id_producto = p.id
                    {$whereClause}
                    GROUP BY p.id
                    ORDER BY p.nombre ASC";
            
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error en búsqueda avanzada: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener productos con stock bajo (menor al mínimo)
     * 
     * @param int $limite Número máximo de resultados
     * @return array Lista de productos con stock bajo
     */
    public static function obtenerStockBajo($limite = 20)
    {
        try {
            $db = (new Conexion())->conectar();
            $sql = "SELECT 
                        p.id,
                        p.nombre,
                        p.sku,
                        p.stock_minimo,
                        COALESCE(SUM(s.cantidad), 0) AS stock_actual,
                        (p.stock_minimo - COALESCE(SUM(s.cantidad), 0)) as faltante
                    FROM productos p
                    LEFT JOIN stock s ON s.id_producto = p.id
                    WHERE p.activo = TRUE
                    GROUP BY p.id
                    HAVING stock_actual < p.stock_minimo
                    ORDER BY faltante DESC
                    LIMIT :limite";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener productos con stock bajo: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Buscar producto por SKU
     * 
     * @param string $sku SKU del producto
     * @return array|null Producto o null si no existe
     */
    public static function buscarPorSKU($sku)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, sku, nombre, descripcion, precio_usd, categoria_id, marca, activo FROM productos WHERE sku = :sku');
            $stmt->bindValue(':sku', $sku, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al buscar producto por SKU: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Activar o desactivar un producto
     * 
     * @param int $id ID del producto
     * @param bool $activo Estado activo/inactivo
     * @return bool True si se actualizó correctamente
     */
    public static function cambiarEstado($id, $activo)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE productos SET activo = :activo, updated_at = NOW() WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':activo', $activo ? 1 : 0, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al cambiar estado del producto: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Listar productos con todos los filtros y opciones
     * 
     * @param array $filtros Filtros a aplicar
     * @return array Lista de productos
     */
    public static function listarConFiltros($filtros = [])
    {
        try {
            $db = (new Conexion())->conectar();
            $where = [];
            $params = [];
            
            // Filtro por categoría
            if (!empty($filtros['categoria_id'])) {
                $where[] = 'p.categoria_id = :categoria_id';
                $params[':categoria_id'] = $filtros['categoria_id'];
            }
            
            // Filtro por marca
            if (!empty($filtros['marca'])) {
                $where[] = 'p.marca = :marca';
                $params[':marca'] = $filtros['marca'];
            }
            
            // Filtro por rango de precio
            if (isset($filtros['precio_min'])) {
                $where[] = 'p.precio_usd >= :precio_min';
                $params[':precio_min'] = $filtros['precio_min'];
            }
            if (isset($filtros['precio_max'])) {
                $where[] = 'p.precio_usd <= :precio_max';
                $params[':precio_max'] = $filtros['precio_max'];
            }
            
            // Filtro por stock (bajo, normal, alto, agotado)
            if (!empty($filtros['nivel_stock'])) {
                switch ($filtros['nivel_stock']) {
                    case 'agotado':
                        $where[] = 'COALESCE(SUM(s.cantidad), 0) <= 0';
                        break;
                    case 'bajo':
                        $where[] = 'COALESCE(SUM(s.cantidad), 0) > 0 AND COALESCE(SUM(s.cantidad), 0) < p.stock_minimo';
                        break;
                    case 'alto':
                        $where[] = 'COALESCE(SUM(s.cantidad), 0) > p.stock_maximo';
                        break;
                }
            }
            
            // Filtro por estado activo
            if (isset($filtros['activo'])) {
                $where[] = 'p.activo = :activo';
                $params[':activo'] = $filtros['activo'] ? 1 : 0;
            } else {
                // Por defecto solo activos
                $where[] = 'p.activo = TRUE';
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            $havingClause = '';
            
            // Si hay filtro de nivel_stock, necesitamos HAVING en lugar de WHERE para algunos casos
            if (!empty($filtros['nivel_stock']) && in_array($filtros['nivel_stock'], ['agotado', 'bajo', 'alto'])) {
                // Mover la última condición de WHERE a HAVING
                array_pop($where);
                $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
                
                switch ($filtros['nivel_stock']) {
                    case 'agotado':
                        $havingClause = 'HAVING stock_total <= 0';
                        break;
                    case 'bajo':
                        $havingClause = 'HAVING stock_total > 0 AND stock_total < p.stock_minimo';
                        break;
                    case 'alto':
                        $havingClause = 'HAVING stock_total > p.stock_maximo';
                        break;
                }
            }
            
            $sql = "SELECT 
                        p.id,
                        p.sku,
                        p.nombre,
                        p.descripcion,
                        p.precio_usd,
                        p.categoria_id,
                        p.marca,
                        p.unidad_medida,
                        p.stock_minimo,
                        p.stock_maximo,
                        p.activo,
                        p.imagen_url,
                        COALESCE(SUM(s.cantidad), 0) AS stock_total
                    FROM productos p
                    LEFT JOIN stock s ON s.id_producto = p.id
                    {$whereClause}
                    GROUP BY p.id
                    {$havingClause}
                    ORDER BY p.nombre ASC";
            
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar productos con filtros: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }
}
