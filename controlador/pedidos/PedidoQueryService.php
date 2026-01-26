<?php
/**
 * PedidoQueryService
 * 
 * Servicio especializado para queries y listados de pedidos.
 * Responsabilidad: Obtener datos de pedidos sin modificarlos.
 */

require_once __DIR__ . '/../../utils/Cache.php';

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
        
        // Si es admin, vendedor o repartidor, mostrar todos
        if (isSuperAdmin() || isVendedor() || isRepartidor()) {
            return $pedidos;
        }
        
        // Si es proveedor (Logística o CRM), filtrar solo sus pedidos
        if (isProveedor() || (isset($GLOBALS['API_USER_ROLE']) && $GLOBALS['API_USER_ROLE'] == ROL_PROVEEDOR_CRM)) {
            $userId = getCurrentUserId();
            $filtered = array_filter($pedidos, function($pedido) use ($userId) {
                $pedidoProveedor = isset($pedido['id_proveedor']) ? (int)$pedido['id_proveedor'] : null;
                return $pedidoProveedor === (int)$userId;
            });
            return array_values($filtered);
        }

        // Si es cliente (Logística o CRM), filtrar solo sus pedidos
        if (isCliente() || (isset($GLOBALS['API_USER_ROLE']) && $GLOBALS['API_USER_ROLE'] == ROL_CLIENTE_CRM)) {
            $userId = getCurrentUserId();
            $filtered = array_filter($pedidos, function($pedido) use ($userId) {
                $pedidoCliente = isset($pedido['id_cliente']) ? (int)$pedido['id_cliente'] : null;
                return $pedidoCliente === (int)$userId;
            });
            return array_values($filtered);
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
     * Obtener estados disponibles (con caché de 24 horas)
     * 
     * @return array
     */
    public function obtenerEstados(): array
    {
        return Cache::remember('estados_pedidos', 86400, function() {
            return PedidosModel::obtenerEstados();
        });
    }

    /**
     * Obtener vendedores/repartidores disponibles (con caché de 1 hora)
     * 
     * @return array
     */
    public function obtenerVendedores(): array
    {
        return Cache::remember('vendedores', 3600, function() {
            return PedidosModel::obtenerVendedores();
        });
    }

    /**
     * Obtener repartidores (alias con caché de 1 hora)
     * 
     * @return array
     */
    public function obtenerRepartidores(): array
    {
        return Cache::remember('repartidores', 3600, function() {
            return PedidosModel::obtenerRepartidores();
        });
    }

    /**
     * Obtener productos disponibles (con caché de 10 minutos)
     * 
     * @return array
     */
    public function obtenerProductos(): array
    {
        return Cache::remember('productos', 600, function() {
            return PedidosModel::obtenerProductos();
        });
    }

    /**
     * Obtener proveedores registrados (con caché de 1 hora)
     * 
     * @return array
     */
    public function obtenerProveedores(): array
    {
        return Cache::remember('proveedores', 3600, function() {
            return PedidosModel::obtenerProveedores();
        });
    }

    /**
     * Obtener monedas disponibles (con caché de 2 horas)
     * 
     * @return array
     */
    public function obtenerMonedas(): array
    {
        return Cache::remember('monedas', 7200, function() {
            return PedidosModel::obtenerMonedas();
        });
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
