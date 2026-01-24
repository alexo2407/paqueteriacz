<?php

class LogisticaController {

    /*
     * Dashboard del Cliente Logística
     */
    public function dashboard() {
        require_once "modelo/logistica.php";
        require_once "modelo/pedido.php";
        
        // Verificar sesión y rol (idealmente en la vista o router, pero validamos user id)
        $clienteId = $_SESSION['idUsuario'] ?? 0;
        
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
        // Interpretamos "notificaciones" como actualizaciones recientes de estado
        $notificaciones = LogisticaModel::obtenerNotificacionesCliente($clienteId, 10);
        
        // 2. Histórico Total (con filtros)
        $historial = LogisticaModel::obtenerHistorialCliente($clienteId, $filtros);

        return [
            'notificaciones' => $notificaciones,
            'historial' => $historial,
            'filtros' => $filtros
        ];
    }

    /*
     * Obtener datos de un pedido para ver detalle (Cliente Logística)
     */
    public function obtenerDatosPedido($id) {
        require_once "modelo/logistica.php";
        
        $clienteId = $_SESSION['idUsuario'] ?? 0;
        $id = (int)$id;

        // 1. Obtener Pedido (reutilizamos modelo pedido o logistica, pero validando cliente)
        require_once "modelo/pedido.php";
        $pedido = PedidosModel::obtenerPedidoPorId($id);

        if (!$pedido || $pedido['id_cliente'] != $clienteId) {
            // No existe o no es de este cliente
            return null; // El controlador no debe redirigir si se usa como data provider
        }

        // 2. Obtener Historial de Cambios (Auditoría) para este pedido
        $historialCambios = LogisticaModel::obtenerHistorialCambiosPedido($id);

        return [
            'pedido' => $pedido,
            'historial' => $historialCambios
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

        $clienteId = $_SESSION['idUsuario'] ?? 0;
        $id = (int)$id;

        // Verificar propiedad
        $pedido = PedidosModel::obtenerPedidoPorId($id);
        if (!$pedido || $pedido['id_cliente'] != $clienteId) {
            // No tiene permisos
            header('Location: ' . RUTA_URL . 'logistica/dashboard');
            exit;
        }

        $nuevoEstado = $_POST['estado'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';

        if (!empty($nuevoEstado)) {
            LogisticaModel::actualizarEstado($id, $nuevoEstado, $observaciones, $clienteId);
        }

        // Redireccionar
        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dashboard') !== false) {
             header('Location: ' . RUTA_URL . 'logistica/dashboard?msg=actualizado');
        } else {
             header('Location: ' . RUTA_URL . 'logistica/ver/' . $id);
        }
        exit;
    }
}
