<?php

class DashboardController {

    /**
     * Obtener todos los datos necesarios para el dashboard.
     * Encapsula la lógica de negocio y preparación de datos para la vista.
     * Si el usuario es proveedor, filtra solo sus pedidos.
     * 
     * @return array
     */
    public function obtenerDatosDashboard() {
        require_once "modelo/pedido.php";
        require_once __DIR__ . '/../utils/permissions.php';

        // Determinar si el usuario es proveedor y obtener su ID
        $proveedorId = null;
        if (isProveedor() && !isSuperAdmin()) {
            $proveedorId = (int)$_SESSION['user_id'];
        }

        // 1. KPIs (filtrados por proveedor si aplica)
        $kpis = PedidosModel::obtenerKPIsMesActual($proveedorId);
        $totalVendido = $kpis['total_vendido'] ?? 0;
        $ticketPromedio = $kpis['ticket_promedio'] ?? 0;
        $totalPedidos = $kpis['total_pedidos'] ?? 0;

        // 2. Ventas Comparativas (filtradas por proveedor si aplica)
        $comparativa = PedidosModel::obtenerVentasComparativa($proveedorId);
        $ventasActual = $comparativa['actual'];
        $ventasAnterior = $comparativa['anterior'];

        // Preparar datos para chart comparativo
        $labelsDias = range(1, 31);
        $dataActual = array_fill(1, 31, 0);
        $dataAnterior = array_fill(1, 31, 0);

        foreach ($ventasActual as $v) {
            $dia = (int)date('d', strtotime($v['fecha']));
            $dataActual[$dia] = (float)$v['total'];
        }
        foreach ($ventasAnterior as $v) {
            $dia = (int)date('d', strtotime($v['fecha']));
            $dataAnterior[$dia] = (float)$v['total'];
        }

        // 3. Ventas Acumuladas (filtradas por proveedor si aplica)
        $acumuladas = PedidosModel::obtenerVentasAcumuladasMesActual($proveedorId);
        $dataAcumulada = [];
        $labelsAcumulada = [];
        foreach ($acumuladas as $ac) {
            $labelsAcumulada[] = date('d/m', strtotime($ac['fecha']));
            $dataAcumulada[] = $ac['total_acumulado'];
        }

        // 4. Top Productos (filtrados por proveedor si aplica)
        $topProductos = PedidosModel::obtenerTopProductosMesActual($proveedorId);
        $nombresProd = [];
        $cantidadesProd = [];
        foreach ($topProductos as $prod) {
            $nombresProd[] = $prod['nombre'];
            $cantidadesProd[] = (int)$prod['total'];
        }

        return [
            'kpis' => [
                'totalVendido' => $totalVendido,
                'ticketPromedio' => $ticketPromedio,
                'totalPedidos' => $totalPedidos
            ],
            'comparativa' => [
                'labels' => array_values($labelsDias),
                'actual' => array_values($dataActual),
                'anterior' => array_values($dataAnterior)
            ],
            'acumulada' => [
                'labels' => $labelsAcumulada,
                'data' => $dataAcumulada
            ],
            'topProductos' => [
                'nombres' => $nombresProd,
                'cantidades' => $cantidadesProd
            ],
            'esProveedor' => $proveedorId !== null // Para mostrar mensaje en la vista
        ];
    }
}
