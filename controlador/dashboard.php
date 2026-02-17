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

        // 1. KPIs de Efectividad (reemplaza métricas de dinero)
        $kpis = PedidosModel::obtenerKPIsEfectividad($proveedorId, $fechaDesde, $fechaHasta);

        // 2. Comparativa de Efectividad (reemplaza comparativa de ventas)
        $comparativa = PedidosModel::obtenerComparativaEfectividad($proveedorId, $fechaDesde, $fechaHasta);

        // 3. Entregas Acumuladas (reemplaza ventas acumuladas)
        $acumulada = PedidosModel::obtenerEntregasAcumuladas($proveedorId, $fechaDesde, $fechaHasta);

        // 4. Top Productos con Efectividad
        $topProductos = PedidosModel::obtenerTopProductosConEfectividad($proveedorId, $fechaDesde, $fechaHasta, 5);

        // 5. Distribución de Estados (nuevo)
        $distribucionEstados = PedidosModel::obtenerDistribucionEstados($proveedorId, $fechaDesde, $fechaHasta);

        // 5. Efectividad por País (para clientes)
        $efectividadPaises = [];
        if ($proveedorId !== null) {
            // Si es proveedor/cliente, mostrar su efectividad
            $efectividadPaises = PedidosModel::obtenerEfectividadPorPais($proveedorId, $fechaDesde, $fechaHasta);
        }

        // 6. Efectividad Temporal y listas para filtros (solo para admin)
        $efectividadTemporal = [];
        $clientes = [];
        $paises = [];
        if (isAdmin()) {
            // Obtener listas para filtros
            require_once "modelo/usuario.php";
            require_once "modelo/pais.php";
            
            $clientes = UsuarioModel::listarClientes();
            $paises = PaisModel::listar();
            
            // Cargar datos iniciales (sin filtros específicos)
            $efectividadTemporal = PedidosModel::obtenerEfectividadTemporal(null, null, $fechaDesde, $fechaHasta);
        }

        return [
            'kpis' => $kpis,
            'comparativa' => $comparativa,
            'acumulada' => $acumulada,
            'topProductos' => $topProductos,
            'distribucionEstados' => $distribucionEstados,
            'efectividadPaises' => $efectividadPaises,
            'efectividadTemporal' => $efectividadTemporal,
            'clientes' => $clientes,
            'paises' => $paises,
            'esProveedor' => $proveedorId !== null,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta
        ];
    }
}
