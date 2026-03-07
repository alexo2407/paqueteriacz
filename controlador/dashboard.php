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

        // Detectar rol de admin con doble verificación (roles_nombres Y isSuperAdmin)
        $rolesNombres   = $_SESSION['roles_nombres'] ?? [];
        $esAdminDash    = in_array('Administrador', $rolesNombres, true) || isSuperAdmin();
        // NOTA: Los roles están invertidos en config.php por diseño histórico:
        //   isProveedor() → detecta rol 'Cliente' (ID4 en BD) → son los CLIENTES logísticos (NutraTrade, Pulox)
        //   isCliente()   → detecta rol 'Proveedor' (ID5 en BD) → son los MENSAJEROS (RutaEX NutraTrade, Pulox CR)
        $esClienteDash  = isProveedor() && !$esAdminDash;  // clientes logísticos (NutraTrade, Pulox)
        $esProveedorDash = isCliente()  && !$esAdminDash;  // mensajeros reales (RutaEX NutraTrade, Pulox CR)

        $userIdActual    = getCurrentUserId();

        // ── Filtro general (usado en KPIs, gráficos, productos) ───────────────
        // Para proveedores: filtra pedidos donde id_proveedor = su ID
        // Para clientes:    filtra pedidos donde id_cliente   = su ID
        // Para admin:       null (sin filtro) o el cliente seleccionado en el desplegable
        $proveedorId     = null;  // alias histórico; se usa como "id_cliente" en las queries
        $clienteIdFiltro = null;  // filtro para el BI de proveedores de mensajería

        if ($esAdminDash) {
            $clienteIdFiltro = isset($_GET['cliente_id']) && (int)$_GET['cliente_id'] > 0
                ? (int)$_GET['cliente_id']
                : null;
            $proveedorId = $clienteIdFiltro; // KPIs filtrados por ese cliente (o todos si null)

        } elseif ($esProveedorDash) {
            $proveedorId     = $userIdActual; // KPIs filtrados por su ID de proveedor
            $clienteIdFiltro = null;          // El BI de mensajería no aplica filtro de cliente para proveedores

        } elseif ($esClienteDash) {
            $clienteIdFiltro = $userIdActual; // BI: solo proveedores que atienden a este cliente
            $proveedorId     = $userIdActual; // KPIs: solo sus pedidos
        }

        // Seguridad: si es cliente/proveedor y no tenemos ID de sesión, no mostrar nada
        if (($esProveedorDash || $esClienteDash) && !$proveedorId) {
            $proveedorId     = -1;
            $clienteIdFiltro = $esClienteDash ? -1 : null;
        }

        // Obtener fechas del filtro (GET) o usar mes actual por defecto
        $fechaDesde = $_GET['fecha_desde'] ?? null;
        $fechaHasta = $_GET['fecha_hasta'] ?? null;
        
        if ($fechaDesde && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) { $fechaDesde = null; }
        if ($fechaHasta && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) { $fechaHasta = null; }
        if (!$fechaDesde) { $fechaDesde = date('Y-m-01'); }
        if (!$fechaHasta) { $fechaHasta = date('Y-m-t');  }

        // 1. KPIs de Efectividad
        $kpis = PedidosModel::obtenerKPIsEfectividad($proveedorId, $fechaDesde, $fechaHasta);

        // 2. Comparativa de Efectividad
        $comparativa = PedidosModel::obtenerComparativaEfectividad($proveedorId, $fechaDesde, $fechaHasta);

        // 3. Entregas Acumuladas
        $acumulada = PedidosModel::obtenerEntregasAcumuladas($proveedorId, $fechaDesde, $fechaHasta);

        // 4. Top Productos con detalle
        $topProductosDetalle = PedidosModel::obtenerTopProductosDetalle($proveedorId, $fechaDesde, $fechaHasta, 8);

        // Build donut chart arrays from detail data
        $topProductos = [
            'nombres'    => array_column(array_slice($topProductosDetalle, 0, 5), 'nombre'),
            'cantidades' => array_map('intval', array_column(array_slice($topProductosDetalle, 0, 5), 'total_unidades'))
        ];

        // 5. Recomendación de producto: el más movido del período
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

        // 6. Distribución de Estados
        $distribucionEstados = PedidosModel::obtenerDistribucionEstados($proveedorId, $fechaDesde, $fechaHasta);

        // 7. Efectividad por País
        $efectividadPaises = [];
        if ($proveedorId !== null) {
            $efectividadPaises = PedidosModel::obtenerEfectividadPorPais($proveedorId, $fechaDesde, $fechaHasta);
        }

        // 8. Listas para filtros y efectividad temporal (solo admin)
        $efectividadTemporal = [];
        $clientes = [];
        $paises   = [];
        if ($esAdminDash) {
            require_once "modelo/usuario.php";
            require_once "modelo/pais.php";
            $clientes = UsuarioModel::listarClientes();
            $paises   = PaisModel::listar();
            $efectividadTemporal = PedidosModel::obtenerEfectividadTemporal($clienteIdFiltro, null, $fechaDesde, $fechaHasta);
        }

        // 9. BI: Ranking proveedores de mensajería
        // $clienteIdFiltro determina el alcance:
        //   null  → admin sin selección   → todos los proveedores
        //   int   → cliente/admin+filtro  → solo proveedores que atienden ese cliente
        $proveedoresMensajeria = PedidosModel::obtenerProveedoresMensajeriaBI($fechaDesde, $fechaHasta, $clienteIdFiltro);


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
            'clienteIdFiltro'       => $clienteIdFiltro,
            'fechaDesde'            => $fechaDesde,
            'fechaHasta'            => $fechaHasta
        ];

    }
}
