<?php
/**
 * PedidoQueryService
 * 
 * Servicio especializado para queries y listados de pedidos.
 * Responsabilidad: Obtener datos de pedidos sin modificarlos.
 */

class PedidoQueryService
{
    /**
     * Listar pedidos con información extendida
     * 
     * @param array|null $filtros Filtros opcionales
     * @return array
     */
    public function listarExtendidos(?array $filtros = null): array
    {
        require_once __DIR__ . '/../../utils/permissions.php';
        
        $pedidos = PedidosModel::obtenerPedidosExtendidos();
        
        // Si es admin, mostrar todos
        if (isSuperAdmin()) {
            return $pedidos;
        }
        
        // Si es proveedor, filtrar solo sus pedidos
        if (isProveedor()) {
            $userId = (int)$_SESSION['user_id'];
            return array_filter($pedidos, function($pedido) use ($userId) {
                $pedidoProveedor = isset($pedido['id_proveedor']) ? (int)$pedido['id_proveedor'] : null;
                return $pedidoProveedor === $userId;
            });
        }
        
        return [];
    }

    /**
     * Obtener un pedido por ID
     * 
     * @param int $id
     * @return array|null
     */
    public function obtenerPorId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        
        return PedidosModel::obtenerPedidoPorId($id);
    }

    /**
     * Listar pedidos asignados a un usuario (repartidor)
     * 
     * @param int $userId
     * @return array
     */
    public function listarAsignados(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        
        return PedidosModel::listarPorUsuarioAsignado($userId);
    }

    /**
     * Obtener estados disponibles
     * 
     * @return array
     */
    public function obtenerEstados(): array
    {
        return PedidosModel::obtenerEstados();
    }

    /**
     * Obtener vendedores/repartidores disponibles
     * 
     * @return array
     */
    public function obtenerVendedores(): array
    {
        return PedidosModel::obtenerVendedores();
    }

    /**
     * Obtener repartidores (alias de obtenerVendedores)
     * 
     * @return array
     */
    public function obtenerRepartidores(): array
    {
        return PedidosModel::obtenerRepartidores();
    }

    /**
     * Obtener productos disponibles
     * 
     * @return array
     */
    public function obtenerProductos(): array
    {
        return PedidosModel::obtenerProductos();
    }

    /**
     * Obtener proveedores registrados
     * 
     * @return array
     */
    public function obtenerProveedores(): array
    {
        return PedidosModel::obtenerProveedores();
    }

    /**
     * Obtener monedas disponibles
     * 
     * @return array
     */
    public function obtenerMonedas(): array
    {
        return PedidosModel::obtenerMonedas();
    }

    /**
     * Buscar pedido por número de orden
     * 
     * @param int|string $numeroOrden
     * @return array ['success' => bool, 'message' => string, 'data' => array|null]
     */
    public function buscarPorNumero($numeroOrden): array
    {
        try {
            $model = new PedidosModel();
            $res = $model->obtenerPedidoPorNumero($numeroOrden);
            
            if ($res) {
                return [
                    'success' => true,
                    'message' => 'Pedido encontrado',
                    'data' => $res
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Pedido no encontrado',
                'data' => null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al buscar pedido: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
}
