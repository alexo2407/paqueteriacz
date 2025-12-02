<?php
include_once __DIR__ . '/conexion.php';

/**
 * BarrioModel
 *
 * Operaciones CRUD y búsquedas por municipio para la entidad barrios.
 */
class BarrioModel
{
    /**
     * Listar barrios, opcionalmente filtrados por municipio.
     *
     * @param int|null $munId Identificador del municipio o null para todos.
     * @return array Lista de barrios (arrays asociativos).
     */
    public static function listarPorMunicipio($munId = null)
    {
        $db = (new Conexion())->conectar();
        if ($munId !== null) {
            $stmt = $db->prepare('SELECT id, nombre, id_municipio FROM barrios WHERE id_municipio = :mid ORDER BY nombre ASC');
            $stmt->bindValue(':mid', (int)$munId, PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare('SELECT id, nombre, id_municipio FROM barrios ORDER BY nombre ASC');
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener un barrio por su id.
     *
     * @param int $id
     * @return array|null Array asociativo con el barrio o null si no existe.
     */
    public static function obtenerPorId($id)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('SELECT id, nombre, id_municipio FROM barrios WHERE id = :id');
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Crear un nuevo barrio para un municipio dado.
     *
     * @param string $nombre
     * @param int $id_municipio
     * @return int ID insertado.
     */
    public static function crear($nombre, $id_municipio)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('INSERT INTO barrios (nombre, id_municipio) VALUES (:nombre, :id_municipio)');
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':id_municipio', (int)$id_municipio, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$db->lastInsertId();
    }

    /**
     * Actualizar los datos de un barrio.
     *
     * @param int $id
     * @param string $nombre
     * @param int $id_municipio
     * @return bool True si la actualización fue exitosa.
     */
    public static function actualizar($id, $nombre, $id_municipio)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('UPDATE barrios SET nombre = :nombre, id_municipio = :id_municipio WHERE id = :id');
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':id_municipio', (int)$id_municipio, PDO::PARAM_INT);
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Eliminar un barrio por su id.
     *
     * @param int $id
     * @return bool True si la eliminación fue exitosa.
     */
    public static function eliminar($id)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('DELETE FROM barrios WHERE id = :id');
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
