<?php
include_once __DIR__ . '/conexion.php';

/**
 * DepartamentoModel
 *
 * CRUD y búsquedas por país para la entidad departamentos.
 */
class DepartamentoModel
{
    /**
     * Listar departamentos, opcionalmente filtrados por país.
     *
     * @param int|null $paisId
     * @return array Lista de departamentos.
     */
    public static function listarPorPais($paisId = null)
    {
        $db = (new Conexion())->conectar();
        if ($paisId !== null) {
            $stmt = $db->prepare('SELECT id, nombre, id_pais FROM departamentos WHERE id_pais = :pid ORDER BY nombre ASC');
            $stmt->bindValue(':pid', (int)$paisId, PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare('SELECT id, nombre, id_pais FROM departamentos ORDER BY nombre ASC');
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener departamento por id.
     * @param int $id
     * @return array|null
     */
    public static function obtenerPorId($id)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('SELECT id, nombre, id_pais FROM departamentos WHERE id = :id');
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Crear un departamento asociado a un país.
     * @param string $nombre
     * @param int $id_pais
     * @return int ID creado
     */
    public static function crear($nombre, $id_pais)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('INSERT INTO departamentos (nombre, id_pais) VALUES (:nombre, :id_pais)');
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':id_pais', (int)$id_pais, PDO::PARAM_INT);
        $stmt->execute();
        return (int)$db->lastInsertId();
    }

    /**
     * Actualizar un departamento.
     * @param int $id
     * @param string $nombre
     * @param int $id_pais
     * @return bool True si se actualizó.
     */
    public static function actualizar($id, $nombre, $id_pais)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('UPDATE departamentos SET nombre = :nombre, id_pais = :id_pais WHERE id = :id');
        $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
        $stmt->bindValue(':id_pais', (int)$id_pais, PDO::PARAM_INT);
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Eliminar un departamento por id.
     * @param int $id
     * @return bool True si eliminado.
     */
    public static function eliminar($id)
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('DELETE FROM departamentos WHERE id = :id');
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
