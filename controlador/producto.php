<?php

require_once __DIR__ . '/../modelo/producto.php';

/**
 * ProductosController
 *
 * Controlador para operaciones CRUD de productos y consultas relacionadas
 * con inventario. Valida entradas mínimas y delega persistencia a
 * `ProductoModel`.
 */
class ProductosController
{
    /**
     * Listar productos con inventario
     * @return array
     */
    public function listar()
    {
        return ProductoModel::listarConInventario();
    }

    /**
     * Obtener detalle de producto
     * @param int $id
     * @return array|null
     */
    public function ver($id)
    {
        $p = ProductoModel::obtenerPorId($id);
        if (!$p) return null;
        $p['stock_total'] = ProductoModel::obtenerStockTotal($id);
        return $p;
    }

    /**
     * Crear producto desde formulario/data
     * @param array $data ['nombre','descripcion','precio_usd']
     * @return array ['success'=>bool,'message'=>string,'id'=>int|null]
     */
    public function crear(array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') {
            return ['success' => false, 'message' => 'El nombre es obligatorio.', 'id' => null];
        }

        $sku         = $data['sku']         ?? null;
        $descripcion = $data['descripcion'] ?? null;
        $precio      = $data['precio_usd']  ?? null;

        // Obtener el usuario actual de la sesión para asignar como creador
        $idUsuarioCreador = (int)($_SESSION['idUsuario'] ?? 0) ?: null;

        $id = ProductoModel::crear($nombre, $sku, $descripcion, $precio, $idUsuarioCreador);
        if ($id === null) {
            return ['success' => false, 'message' => 'No fue posible crear el producto.', 'id' => null];
        }
        return ['success' => true, 'message' => 'Producto creado correctamente.', 'id' => $id];
    }

    /**
     * Actualizar producto
     * @param int $id
     * @param array $data
     * @return array
     */
    public function actualizar($id, array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') {
            return ['success' => false, 'message' => 'El nombre es obligatorio.'];
        }

        // Pasar el array completo al modelo para que maneje todos los campos
        $ok = ProductoModel::actualizar($id, $data);
        if (!$ok) {
            return ['success' => false, 'message' => 'No fue posible actualizar el producto.'];
        }
        return ['success' => true, 'message' => 'Producto actualizado correctamente.'];
    }

    /**
     * Eliminar producto
     * @param int $id
     * @return array
     */
    public function eliminar($id)
    {
        if ($id <= 0) return ['success' => false, 'message' => 'ID inválido.'];
        $ok = ProductoModel::eliminar($id);
        if (!$ok) return ['success' => false, 'message' => 'No fue posible eliminar el producto.'];
        return ['success' => true, 'message' => 'Producto eliminado correctamente.'];
    }
}
