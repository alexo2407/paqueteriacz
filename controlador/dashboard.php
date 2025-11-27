<?php

class DashboardController {

    /**
     * Obtener todos los datos necesarios para el dashboard.
     * Encapsula la lógica de negocio y preparación de datos para la vista.
     * 
     * @return array
     */
    public function obtenerDatosDashboard() {
        require_once "modelo/pedido.php";

        // 1. KPIs
        $kpis = PedidosModel::obtenerKPIsMesActual();
        $totalVendido = $kpis['total_vendido'] ?? 0;
        $ticketPromedio = $kpis['ticket_promedio'] ?? 0;
        $totalPedidos = $kpis['total_pedidos'] ?? 0;

        // 2. Ventas Comparativas
        $comparativa = PedidosModel::obtenerVentasComparativa();
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

        // 3. Ventas Acumuladas
        $acumuladas = PedidosModel::obtenerVentasAcumuladasMesActual();
        $dataAcumulada = [];
        $labelsAcumulada = [];
        foreach ($acumuladas as $ac) {
            $labelsAcumulada[] = date('d/m', strtotime($ac['fecha']));
            $dataAcumulada[] = $ac['total_acumulado'];
        }

        // 4. Top Productos
        $topProductos = PedidosModel::obtenerTopProductosMesActual();
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
            ]
        ];
    }
}
