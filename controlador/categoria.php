<?php

require_once __DIR__ . '/../modelo/categoria.php';

/**
 * CategoriaController
 *
 * Controlador para operaciones CRUD de categorías de productos.
 * Gestiona categorías jerárquicas y valida datos antes de persistir.
 */
class CategoriaController
{
    /**
     * Listar todas las categorías
     * 
     * @param bool $incluirInactivas Si incluir categorías inactivas
     * @return array Lista de categorías
     */
    public function listar($incluirInactivas = false)
    {
        return CategoriaModel::listar($incluirInactivas);
    }

    /**
     * Listar categorías en formato jerárquico
     * 
     * @return array Categorías organizadas con subcategorías
     */
    public function listarJerarquico()
    {
        return CategoriaModel::listarJerarquico();
    }

    /**
     * Obtener una categoría por ID
     * 
     * @param int $id ID de la categoría
     * @return array|null Categoría o null si no existe
     */
    public function ver($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return null;
        }
        return CategoriaModel::obtenerPorId((int)$id);
    }

    /**
     * Crear una nueva categoría
     * 
     * @param array $data ['nombre', 'descripcion', 'padre_id']
     * @return array ['success' => bool, 'message' => string, 'id' => int|null]
     */
    public function crear(array $data)
    {
        // Validación
        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') {
            return [
                'success' => false,
                'message' => 'El nombre de la categoría es obligatorio.',
                'id' => null
            ];
        }

        $descripcion = $data['descripcion'] ?? null;
        $padreId = isset($data['padre_id']) && is_numeric($data['padre_id']) ? (int)$data['padre_id'] : null;

        // Validar que la categoría padre exista si se especificó
        if ($padreId !== null) {
            $padre = CategoriaModel::obtenerPorId($padreId);
            if (!$padre) {
                return [
                    'success' => false,
                    'message' => 'La categoría padre seleccionada no existe.',
                    'id' => null
                ];
            }
        }

        // Crear
        $id = CategoriaModel::crear($nombre, $descripcion, $padreId);
        if ($id === null) {
            return [
                'success' => false,
                'message' => 'No fue posible crear la categoría.',
                'id' => null
            ];
        }

        return [
            'success' => true,
            'message' => 'Categoría creada correctamente.',
            'id' => $id
        ];
    }

    /**
     * Actualizar una categoría existente
     * 
     * @param int $id ID de la categoría
     * @param array $data Datos a actualizar
     * @return array ['success' => bool, 'message' => string]
     */
    public function actualizar($id, array $data)
    {
        if (!is_numeric($id) || $id <= 0) {
            return [
                'success' => false,
                'message' => 'ID de categoría inválido.'
            ];
        }

        // Validación
        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') {
            return [
                'success' => false,
                'message' => 'El nombre de la categoría es obligatorio.'
            ];
        }

        $descripcion = $data['descripcion'] ?? null;
        $padreId = isset($data['padre_id']) && is_numeric($data['padre_id']) ? (int)$data['padre_id'] : null;

        // Validar que no se esté intentando hacer una categoría hija de sí misma
        if ($padreId !== null && $padreId === $id) {
            return [
                'success' => false,
                'message' => 'Una categoría no puede ser su propia categoría padre.'
            ];
        }

        // Validar que la categoría padre exista si se especificó
        if ($padreId !== null) {
            $padre = CategoriaModel::obtenerPorId($padreId);
            if (!$padre) {
                return [
                    'success' => false,
                    'message' => 'La categoría padre seleccionada no existe.'
                ];
            }
        }

        // Actualizar
        $ok = CategoriaModel::actualizar($id, $nombre, $descripcion, $padreId);
        if (!$ok) {
            return [
                'success' => false,
                'message' => 'No fue posible actualizar la categoría.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Categoría actualizada correctamente.'
        ];
    }

    /**
     * Activar o desactivar una categoría
     * 
     * @param int $id ID de la categoría
     * @param bool $activo Estado deseado
     * @return array ['success' => bool, 'message' => string]
     */
    public function cambiarEstado($id, $activo)
    {
        if (!is_numeric($id) || $id <= 0) {
            return [
                'success' => false,
                'message' => 'ID de categoría inválido.'
            ];
        }

        $ok = CategoriaModel::cambiarEstado((int)$id, (bool)$activo);
        if (!$ok) {
            return [
                'success' => false,
                'message' => 'No fue posible cambiar el estado de la categoría.'
            ];
        }

        $estado = $activo ? 'activada' : 'desactivada';
        return [
            'success' => true,
            'message' => "Categoría {$estado} correctamente."
        ];
    }

    /**
     * Eliminar una categoría
     * 
     * @param int $id ID de la categoría
     * @return array ['success' => bool, 'message' => string]
     */
    public function eliminar($id)
    {
        if (!is_numeric($id) || $id <= 0) {
            return [
                'success' => false,
                'message' => 'ID de categoría inválido.'
            ];
        }

        $ok = CategoriaModel::eliminar((int)$id);
        if (!$ok) {
            return [
                'success' => false,
                'message' => 'No fue posible eliminar la categoría. Verifica que no tenga productos asociados ni subcategorías.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Categoría eliminada correctamente.'
        ];
    }

    /**
     * Obtener subcategorías de una categoría
     * 
     * @param int $padreId ID de la categoría padre
     * @return array Lista de subcategorías
     */
    public function obtenerSubcategorias($padreId)
    {
        if (!is_numeric($padreId) || $padreId <= 0) {
            return [];
        }
        return CategoriaModel::obtenerSubcategorias((int)$padreId);
    }

    /**
     * Obtener estadísticas de productos por categoría
     * 
     * @return array Conteo de productos por categoría
     */
    public function obtenerEstadisticas()
    {
        return CategoriaModel::contarProductosPorCategoria();
    }
}
