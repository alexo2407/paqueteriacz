<?php

class LogisticaController {

    /*
     * Dashboard del Cliente Logística
     */
    public function dashboard() {
        require_once "modelo/logistica.php";
        require_once "modelo/pedido.php";
        require_once __DIR__ . '/../utils/permissions.php';
        
        // Verificar sesión y rol
        $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        
        // Determinar si el usuario es proveedor (mensajería) o cliente
        // IMPORTANTE: isCliente() verifica ROL_CLIENTE (ID 5) que en BD se llama "Proveedor"
        // Estos son los proveedores de mensajería que ven pedidos donde id_proveedor = su user_id
        $isProveedor = isCliente();
        
        // Paginación
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 2;
        $offset = ($page - 1) * $perPage;
        
        // Filtros
        $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
        $search = $_GET['search'] ?? '';

        $filtros = [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'search' => $search
        ];

        // 1. Notificaciones (Cambios recientes en mis pedidos)
        // Pasamos el flag $isProveedor para filtrar correctamente
        $notificaciones = LogisticaModel::obtenerNotificacionesCliente($userId, 10, $isProveedor);
        
        // 2. Histórico Total (con filtros y paginación)
        // Pasamos el flag $isProveedor para filtrar correctamente
        // IMPORTANTE: Excluimos estados finales para que la paginación solo cuente pedidos activos
        $historial = LogisticaModel::obtenerHistorialCliente($userId, $filtros, $isProveedor, $perPage, $offset, true);
        
        // 3. Contar total de pedidos para paginación (solo activos)
        $totalPedidos = LogisticaModel::contarPedidos($userId, $filtros, $isProveedor, true);
        $totalPages = ceil($totalPedidos / $perPage);

        // 3. Obtener todos los estados disponibles para el selector
        $estados = LogisticaModel::obtenerEstados();

        return [
            'notificaciones' => $notificaciones,
            'historial' => $historial,
            'estados' => $estados,
            'filtros' => $filtros,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalPedidos,
                'total_pages' => $totalPages
            ]
        ];
    }

    /*
     * Obtener datos de un pedido para ver detalle (Cliente Logística)
     */
    public function obtenerDatosPedido($id) {
        require_once "modelo/logistica.php";
        require_once __DIR__ . '/../utils/permissions.php';
        
        $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $id = (int)$id;
        // IMPORTANTE: isCliente() verifica ROL_CLIENTE (ID 5) que en BD se llama "Proveedor"
        $isProveedor = isCliente();

        // 1. Obtener Pedido
        require_once "modelo/pedido.php";
        $pedido = PedidosModel::obtenerPedidoPorId($id);

        if (!$pedido) {
            return null;
        }
        
        // 2. Validar propiedad según el rol
        $hasAccess = false;
        if ($isProveedor) {
            // Proveedor: verificar id_proveedor
            $hasAccess = ($pedido['id_proveedor'] == $userId);
        } else {
            // Cliente: verificar id_cliente
            $hasAccess = ($pedido['id_cliente'] == $userId);
        }
        
        if (!$hasAccess) {
            // No tiene permisos
            return null;
        }

        // 3. Obtener Historial de Cambios (Auditoría) para este pedido
        $historialCambios = LogisticaModel::obtenerHistorialCambiosPedido($id);

        // 4. Obtener todos los estados disponibles para el selector
        $estados = LogisticaModel::obtenerEstados();

        return [
            'pedido' => $pedido,
            'historial' => array_reverse($historialCambios), // Revertir para mostrar más recientes primero si listarPorRegistro devuelve ASC
            'estados' => $estados
        ];
    }

    /*
     * Cambiar estado de un pedido (Cliente Logística)
     */
    public function cambiarEstado($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . RUTA_URL . 'logistica/dashboard');
            exit;
        }

        require_once "modelo/logistica.php";
        require_once "modelo/pedido.php";
        require_once __DIR__ . '/../utils/permissions.php';

        $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $id = (int)$id;
        // IMPORTANTE: isCliente() verifica ROL_CLIENTE (ID 5) que en BD se llama "Proveedor"
        $isProveedor = isCliente();

        // Verificar propiedad
        $pedido = PedidosModel::obtenerPedidoPorId($id);
        if (!$pedido) {
            header('Location: ' . RUTA_URL . 'logistica/dashboard');
            exit;
        }
        
        // Validar propiedad según el rol
        $hasAccess = false;
        if ($isProveedor) {
            // Proveedor: verificar id_proveedor
            $hasAccess = ($pedido['id_proveedor'] == $userId);
        } else {
            // Cliente: verificar id_cliente
            $hasAccess = ($pedido['id_cliente'] == $userId);
        }
        
        if (!$hasAccess) {
            // No tiene permisos
            header('Location: ' . RUTA_URL . 'logistica/dashboard');
            exit;
        }

        $nuevoEstado = $_POST['estado'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';

        $success = false;
        if (!empty($nuevoEstado)) {
            $success = LogisticaModel::actualizarEstado($id, $nuevoEstado, $observaciones, $clienteId);
        }

        // Si es una petición AJAX (detectada por header o parámetro)
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            // Limpiar cualquier salida previa (notices, warnings) para asegurar JSON válido
            if (ob_get_length()) ob_clean();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Estado actualizado correctamente' : 'Error al actualizar el estado'
            ]);
            exit;
        }

        // Redireccionar si no es AJAX
        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dashboard') !== false) {
             header('Location: ' . RUTA_URL . 'logistica/dashboard?msg=actualizado');
        } else {
             header('Location: ' . RUTA_URL . 'logistica/ver/' . $id);
        }
        exit;
    }
}
