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
                        p.nombre,
                        p.descripcion,
                        p.precio_usd,
                        COALESCE(SUM(s.cantidad), 0) AS stock_total
                    FROM productos p
                    LEFT JOIN stock s ON s.id_producto = p.id
                    GROUP BY p.id, p.nombre, p.descripcion, p.precio_usd
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
            $stmt = $db->prepare('SELECT id, nombre, descripcion, precio_usd FROM productos WHERE id = :id');
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
     * Actualizar un producto existente
     * @param int $id
     * @param string $nombre
     * @param string|null $descripcion
     * @param float|null $precioUsd
     * @return bool True si se actualizó, False si no o en error
     */
    public static function actualizar($id, $nombre, $descripcion = null, $precioUsd = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE productos SET nombre = :nombre, descripcion = :descripcion, precio_usd = :precio_usd WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
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
}
