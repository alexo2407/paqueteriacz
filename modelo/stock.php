<?php

include_once __DIR__ . '/conexion.php';

/**
 * Class StockModel
 *
 * Acceso a datos para la entidad `stock`.
 * Contiene métodos CRUD básicos. La lógica de movimientos/ajustes de stock
 * debería manejarse por triggers en la base de datos; algunos métodos de
 * manipulación fina están deshabilitados para evitar inconsistencias.
 */
class StockModel
{
    /**
     * NOTA: La gestión de stock está centralizada mediante triggers en la base
     * de datos. Evitar que PHP inserte/actualice movimientos directamente
     * desde los controladores/servicios para no duplicar la lógica.
     *
     * Si es necesario un método PHP para registros ad-hoc, crear uno nuevo
     * con nombre claro y documentarlo. El método registrarSalida está
     * deshabilitado por defecto en este repositorio para proteger la
     * integridad cuando los esquemas de `stock` varían entre despliegues.
     */
    /**
     * Listar todos los registros de stock.
     *
     * @return array Lista de registros (array asociativo). En caso de error devuelve [].
     */
    public static function listar()
    {
        try {
            $db = (new Conexion())->conectar();
            // Devolver información útil para la UI: id, id_usuario, id_producto, producto (nombre) y cantidad
            $stmt = $db->prepare('SELECT s.id, s.id_usuario, s.id_producto, s.cantidad, p.nombre AS producto FROM stock s LEFT JOIN productos p ON p.id = s.id_producto ORDER BY s.id DESC');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }
    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT s.id, s.id_usuario, s.id_producto, s.cantidad, p.nombre AS producto FROM stock s LEFT JOIN productos p ON p.id = s.id_producto WHERE s.id = :id');
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener registro de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Obtener un registro de stock por id.
     *
     * @param int $id
     * @return array|null Registro o null si no existe o en error.
     */

    /**
     * Crear un nuevo registro de stock.
     *
     * @param array $data Asociativo con claves: id_vendedor, producto, cantidad.
     * @return int|false Nuevo id insertado o false en caso de fallo.
     */
    public static function crear(array $data)
    {
        try {
            $db = (new Conexion())->conectar();
            // Ahora asumimos columnas: id_usuario, id_producto, cantidad
            $stmt = $db->prepare('INSERT INTO stock (id_usuario, id_producto, cantidad) VALUES (:id_usuario, :id_producto, :cantidad)');
            $stmt->bindValue(':id_usuario', $data['id_usuario'], PDO::PARAM_INT);
            $stmt->bindValue(':id_producto', $data['id_producto'], PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $data['cantidad'], PDO::PARAM_INT);
            $ok = $stmt->execute();
            return $ok ? (int) $db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log('Error al crear registro de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Actualizar un registro existente.
     *
     * @param int $id Identificador del registro a actualizar.
     * @param array $data Claves: id_vendedor, producto, cantidad.
     * @return bool True si la ejecución fue exitosa, false en caso contrario.
     */
    public static function actualizar($id, array $data)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE stock SET id_usuario = :id_usuario, id_producto = :id_producto, cantidad = :cantidad WHERE id = :id');
            $stmt->bindValue(':id_usuario', $data['id_usuario'], PDO::PARAM_INT);
            $stmt->bindValue(':id_producto', $data['id_producto'], PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $data['cantidad'], PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al actualizar stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Eliminar un registro por id.
     *
     * @param int $id Identificador del registro.
     * @return bool True si eliminado, false si ocurrió un error.
     */
    public static function eliminar($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('DELETE FROM stock WHERE id = :id');
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al eliminar stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Ajustar la cantidad del registro por una diferencia (positiva o negativa).
     *
     * @param int $id Identificador del registro.
     * @param int $diferencia Valor a sumar (puede ser negativo).
     * @return bool True si se afectó alguna fila, false en caso contrario.
     */
    public static function ajustarCantidad($id, $diferencia)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE stock SET cantidad = cantidad + :diferencia WHERE id = :id');
            $stmt->bindValue(':diferencia', $diferencia, PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('Error al ajustar cantidad de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Registrar un movimiento de salida (cantidad negativa) intentando varias
     * estrategias de INSERT para adaptarse a distintos esquemas de la tabla
     * `stock` en diferentes despliegues.
     *
     * @param int $idProducto
     * @param int $cantidad
     * @param int|null $idVendedor
     * @return bool
     * @throws Exception si ninguna estrategia funciona
     */
    public static function registrarSalida($idProducto, $cantidad, $idVendedor = null, $pdo = null)
    {
        try {
            $db = $pdo ? $pdo : (new Conexion())->conectar();
            // Insertar movimiento negativo
            // Asumimos que la cantidad recibida es positiva (cantidad a descontar),
            // por lo que la guardamos como negativa en la tabla.
            $cantidadNegativa = -abs($cantidad);
            
            $stmt = $db->prepare('INSERT INTO stock (id_producto, id_usuario, cantidad, updated_at) VALUES (:id_producto, :id_usuario, :cantidad, NOW())');
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            // Si no hay vendedor, usamos 1 (Admin/Sistema) como fallback para evitar error de FK
            $stmt->bindValue(':id_usuario', $idVendedor ?: 1, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $cantidadNegativa, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al registrar salida de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            // Lanzar excepción para que la transacción superior pueda hacer rollback
            throw new Exception("No se pudo registrar la salida de stock: " . $e->getMessage());
        }
    }

    /**
     * Registrar un movimiento de entrada (cantidad positiva).
     * Útil para devoluciones o correcciones de inventario.
     *
     * @param int $idProducto
     * @param int $cantidad (debe ser positiva)
     * @param int|null $idVendedor
     * @param PDO|null $pdo
     * @return bool
     * @throws Exception
     */
    public static function registrarEntrada($idProducto, $cantidad, $idVendedor = null, $pdo = null)
    {
        try {
            $db = $pdo ? $pdo : (new Conexion())->conectar();
            
            $cantidadPositiva = abs($cantidad);
            
            $stmt = $db->prepare('INSERT INTO stock (id_producto, id_usuario, cantidad, updated_at) VALUES (:id_producto, :id_usuario, :cantidad, NOW())');
            $stmt->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
            $stmt->bindValue(':id_usuario', $idVendedor ?: 1, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $cantidadPositiva, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al registrar entrada de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            throw new Exception("No se pudo registrar la entrada de stock: " . $e->getMessage());
        }
    }
}
