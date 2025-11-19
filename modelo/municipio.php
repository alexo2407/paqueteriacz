<?php
include_once __DIR__ . '/conexion.php';

/**
 * MunicipioModel
 *
 * CRUD y búsquedas por departamento para la entidad municipios.
 */
class MunicipioModel
{
    /**
     * Listar municipios, opcionalmente filtrados por departamento.
     * @param int|null $depId
     * @return array
     */
    public static function listarPorDepartamento($depId = null)
    {
        try {
            $db = (new Conexion())->conectar();
            if ($depId !== null) {
                $stmt = $db->prepare('SELECT id, nombre, id_departamento FROM municipios WHERE id_departamento = :did ORDER BY nombre ASC');
                $stmt->bindValue(':did', (int)$depId, PDO::PARAM_INT);
            } else {
                $stmt = $db->prepare('SELECT id, nombre, id_departamento FROM municipios ORDER BY nombre ASC');
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error listar municipios: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener municipio por id.
     * @param int $id
     * @return array|null
     */
    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, nombre, id_departamento FROM municipios WHERE id = :id');
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error obtener municipio: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Crear un municipio asociado a un departamento.
     * @param string $nombre
     * @param int $id_departamento
     * @return int|null ID creado o null en error
     */
    public static function crear($nombre, $id_departamento)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO municipios (nombre, id_departamento) VALUES (:nombre, :id_departamento)');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':id_departamento', (int)$id_departamento, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log('Error crear municipio: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Actualizar un municipio.
     * @param int $id
     * @param string $nombre
     * @param int $id_departamento
     * @return bool True si se actualizó.
     */
    public static function actualizar($id, $nombre, $id_departamento)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE municipios SET nombre = :nombre, id_departamento = :id_departamento WHERE id = :id');
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            $stmt->bindValue(':id_departamento', (int)$id_departamento, PDO::PARAM_INT);
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error actualizar municipio: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Eliminar un municipio por id.
     * @param int $id
     * @return bool True si eliminado.
     */
    public static function eliminar($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('DELETE FROM municipios WHERE id = :id');
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error eliminar municipio: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }
}
