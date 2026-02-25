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

        // Obtener fechas del filtro (GET) o usar mes actual por defecto
        $fechaDesde = $_GET['fecha_desde'] ?? null;
        $fechaHasta = $_GET['fecha_hasta'] ?? null;
        
        // Validar formato de fechas si se proporcionan
        if ($fechaDesde && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
            $fechaDesde = null;
        }
        if ($fechaHasta && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
            $fechaHasta = null;
        }
        
        // Si no se proporcionan fechas, usar mes actual
        if (!$fechaDesde) {
            $fechaDesde = date('Y-m-01');
        }
        if (!$fechaHasta) {
            $fechaHasta = date('Y-m-t');
        }

        // 1. KPIs de Efectividad
        $kpis = PedidosModel::obtenerKPIsEfectividad($proveedorId, $fechaDesde, $fechaHasta);

        // 2. Comparativa de Efectividad
        $comparativa = PedidosModel::obtenerComparativaEfectividad($proveedorId, $fechaDesde, $fechaHasta);

        // 3. Entregas Acumuladas
        $acumulada = PedidosModel::obtenerEntregasAcumuladas($proveedorId, $fechaDesde, $fechaHasta);

        // 4. Top Productos con detalle (todos los pedidos del período, no solo entregados)
        $topProductosDetalle = PedidosModel::obtenerTopProductosDetalle($proveedorId, $fechaDesde, $fechaHasta, 8);

        // Build donut chart arrays from detail data
        $topProductos = [
            'nombres'    => array_column(array_slice($topProductosDetalle, 0, 5), 'nombre'),
            'cantidades' => array_map('intval', array_column(array_slice($topProductosDetalle, 0, 5), 'total_unidades'))
        ];

        // 6. Recomendación de producto: el más movido del período
        $recomendacion = null;
        if (!empty($topProductosDetalle)) {
            $top = $topProductosDetalle[0];
            $recomendacion = [
                'nombre'         => $top['nombre'],
                'total_unidades' => (int)$top['total_unidades'],
                'total_pedidos'  => (int)$top['total_pedidos'],
                'efectividad'    => (float)$top['efectividad'],
            ];
        }

        // 7. Distribución de Estados
        $distribucionEstados = PedidosModel::obtenerDistribucionEstados($proveedorId, $fechaDesde, $fechaHasta);

        // 8. Efectividad por País (para proveedores/clientes)
        $efectividadPaises = [];
        if ($proveedorId !== null) {
            $efectividadPaises = PedidosModel::obtenerEfectividadPorPais($proveedorId, $fechaDesde, $fechaHasta);
        }

        // 9. Efectividad Temporal y listas para filtros (solo admin)
        $efectividadTemporal = [];
        $clientes = [];
        $paises   = [];
        if (isAdmin()) {
            require_once "modelo/usuario.php";
            require_once "modelo/pais.php";
            $clientes = UsuarioModel::listarClientes();
            $paises   = PaisModel::listar();
            $efectividadTemporal = PedidosModel::obtenerEfectividadTemporal(null, null, $fechaDesde, $fechaHasta);
        }

        // 10. BI: Ranking proveedores de mensajería por efectividad
        $proveedoresMensajeria = PedidosModel::obtenerProveedoresMensajeriaBI($fechaDesde, $fechaHasta);

        return [
            'kpis'                  => $kpis,
            'comparativa'           => $comparativa,
            'acumulada'             => $acumulada,
            'topProductos'          => $topProductos,
            'topProductosDetalle'   => $topProductosDetalle,
            'recomendacion'         => $recomendacion,
            'distribucionEstados'   => $distribucionEstados,
            'efectividadPaises'     => $efectividadPaises,
            'efectividadTemporal'   => $efectividadTemporal,
            'clientes'              => $clientes,
            'paises'                => $paises,
            'proveedoresMensajeria' => $proveedoresMensajeria,
            'esProveedor'           => $proveedorId !== null,
            'fechaDesde'            => $fechaDesde,
            'fechaHasta'            => $fechaHasta
        ];
    }
}
