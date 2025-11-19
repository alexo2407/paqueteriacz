<?php
include_once __DIR__ . '/conexion.php';

/**
 * PaisModel
 *
 * CRUD para tabla `paises`.
 */
class PaisModel
{
    /**
     * Listar países ordenados por nombre.
     * @return array Lista de países o [] en caso de error.
     */
    public static function listar()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, nombre, codigo_iso FROM paises ORDER BY nombre ASC');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error listar paises: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener país por id.
     * @param int $id
     * @return array|null Array asociativo o null si no existe.
     */
    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, nombre, codigo_iso FROM paises WHERE id = :id');
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error obtener pais: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Crear un nuevo país.
     * @param string $nombre
     * @param string|null $codigo_iso
     * @return int|null ID creado o null en error
     */
    public static function crear($nombre, $codigo_iso = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO paises (nombre, codigo_iso) VALUES (:nombre, :codigo_iso)');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':codigo_iso', $codigo_iso ?: null, $codigo_iso ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->execute();
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error crear pais: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Actualizar un país.
     * @param int $id
     * @param string $nombre
     * @param string|null $codigo_iso
     * @return bool True si se actualizó.
     */
    public static function actualizar($id, $nombre, $codigo_iso = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE paises SET nombre = :nombre, codigo_iso = :codigo_iso WHERE id = :id');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':codigo_iso', $codigo_iso ?: null, $codigo_iso ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error actualizar pais: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Eliminar país por id.
     * @param int $id
     * @return bool True si se eliminó correctamente.
     */
    public static function eliminar($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('DELETE FROM paises WHERE id = :id');
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error eliminar pais: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }
}
