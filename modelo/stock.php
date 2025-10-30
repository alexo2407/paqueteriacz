<?php

include_once __DIR__ . '/conexion.php';

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
    public static function listar()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, id_vendedor, producto, cantidad FROM stock');
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
            $stmt = $db->prepare('SELECT id, id_vendedor, producto, cantidad FROM stock WHERE id = :id');
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener registro de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    public static function crear(array $data)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO stock (id_vendedor, producto, cantidad) VALUES (:id_vendedor, :producto, :cantidad)');
            $stmt->bindValue(':id_vendedor', $data['id_vendedor'], PDO::PARAM_INT);
            $stmt->bindValue(':producto', $data['producto'], PDO::PARAM_STR);
            $stmt->bindValue(':cantidad', $data['cantidad'], PDO::PARAM_INT);
            $ok = $stmt->execute();
            return $ok ? (int) $db->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log('Error al crear registro de stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    public static function actualizar($id, array $data)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE stock SET id_vendedor = :id_vendedor, producto = :producto, cantidad = :cantidad WHERE id = :id');
            $stmt->bindValue(':id_vendedor', $data['id_vendedor'], PDO::PARAM_INT);
            $stmt->bindValue(':producto', $data['producto'], PDO::PARAM_STR);
            $stmt->bindValue(':cantidad', $data['cantidad'], PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al actualizar stock: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

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
    public static function registrarSalida($idProducto, $cantidad, $idVendedor = null)
    {
        // En esta rama hemos decidido delegar la gestión de stock a triggers en
        // la base de datos. El método PHP queda deshabilitado para evitar
        // inserciones que puedan fallar por diferencias de esquema.
        throw new Exception('Registro de salida de stock deshabilitado en PHP: use triggers en la base de datos.');
    }
}
