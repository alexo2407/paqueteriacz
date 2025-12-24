<?php

include_once __DIR__ . '/conexion.php';
include_once __DIR__ . '/auditoria.php';
/**
 * MonedaModel
 *
 * Operaciones CRUD y búsquedas relacionadas con la tabla `monedas`.
 * Todos los métodos devuelven datos primitivos/arrays o valores booleanos
 * en caso de error. Los errores se registran en logs/errors.log.
 */
class MonedaModel
{
    /**
     * Listar todas las monedas.
     *
     * @return array Lista de monedas (arrays asociativos) o [] en caso de error.
     */
    public static function listar()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, codigo, nombre, tasa_usd FROM monedas ORDER BY nombre ASC');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al listar monedas: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return [];
        }
    }

    /**
     * Obtener moneda por id.
     *
     * @param int $id
     * @return array|null Array asociativo con la moneda o null si no existe.
     */
    public static function obtenerPorId($id)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, codigo, nombre, tasa_usd FROM monedas WHERE id = :id');
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error al obtener moneda: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Crear una nueva moneda.
     *
     * @param string $codigo
     * @param string $nombre
     * @param float|null $tasa_usd
     * @return int|null ID insertado o null en caso de error.
     */
    public static function crear($codigo, $nombre, $tasa_usd = null)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('INSERT INTO monedas (codigo, nombre, tasa_usd) VALUES (:codigo, :nombre, :tasa_usd)');
            $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            if ($tasa_usd === null || $tasa_usd === '') {
                $stmt->bindValue(':tasa_usd', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':tasa_usd', $tasa_usd);
            }
            $stmt->execute();
            $nuevoId = (int)$db->lastInsertId();
            
            // Registrar auditoría
            AuditoriaModel::registrar(
                'monedas',
                $nuevoId,
                'crear',
                AuditoriaModel::getIdUsuarioActual(),
                null,
                ['codigo' => $codigo, 'nombre' => $nombre, 'tasa_usd' => $tasa_usd]
            );
            
            return $nuevoId;
        } catch (PDOException $e) {
            error_log('Error crear moneda: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }

    /**
     * Actualizar una moneda existente.
     *
     * @param int $id
     * @param string $codigo
     * @param string $nombre
     * @param float|null $tasa_usd
     * @return bool True si la actualización tuvo éxito.
     */
    public static function actualizar($id, $codigo, $nombre, $tasa_usd = null)
    {
        try {
            // Obtener datos anteriores para auditoría
            $datosAnteriores = self::obtenerPorId($id);
            
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('UPDATE monedas SET codigo = :codigo, nombre = :nombre, tasa_usd = :tasa_usd WHERE id = :id');
            $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->bindValue(':nombre', $nombre, PDO::PARAM_STR);
            if ($tasa_usd === null || $tasa_usd === '') {
                $stmt->bindValue(':tasa_usd', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':tasa_usd', $tasa_usd);
            }
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $resultado = $stmt->execute();
            
            if ($resultado) {
                // Registrar auditoría
                AuditoriaModel::registrar(
                    'monedas',
                    $id,
                    'actualizar',
                    AuditoriaModel::getIdUsuarioActual(),
                    $datosAnteriores,
                    ['codigo' => $codigo, 'nombre' => $nombre, 'tasa_usd' => $tasa_usd]
                );
            }
            
            return $resultado;
        } catch (PDOException $e) {
            error_log('Error actualizar moneda: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Eliminar una moneda por id.
     *
     * @param int $id
     * @return bool True si se eliminó correctamente.
     */
    public static function eliminar($id)
    {
        try {
            // Obtener datos antes de eliminar para auditoría
            $datosAnteriores = self::obtenerPorId($id);
            
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('DELETE FROM monedas WHERE id = :id');
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $resultado = $stmt->execute();
            
            if ($resultado && $datosAnteriores) {
                // Registrar auditoría
                AuditoriaModel::registrar(
                    'monedas',
                    $id,
                    'eliminar',
                    AuditoriaModel::getIdUsuarioActual(),
                    $datosAnteriores,
                    null
                );
            }
            
            return $resultado;
        } catch (PDOException $e) {
            error_log('Error eliminar moneda: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return false;
        }
    }

    /**
     * Buscar una moneda por su código.
     *
     * @param string $codigo
     * @return array|null Array asociativo con la moneda o null si no existe.
     */
    public static function buscarPorCodigo($codigo)
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT id, codigo, nombre, tasa_usd FROM monedas WHERE codigo = :codigo LIMIT 1');
            $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log('Error buscar moneda: ' . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
            return null;
        }
    }
}
