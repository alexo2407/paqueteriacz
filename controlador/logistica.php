<?php

class LogisticaController {

    /*
     * Dashboard del Cliente Logística
     */
    public function dashboard() {
        require_once "modelo/logistica.php";
        require_once "modelo/pedido.php";
        require_once __DIR__ . '/../utils/permissions.php';
        
        $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        
        // IMPORTANTE: isCliente() verifica ROL_CLIENTE (ID 5) que en BD se llama "Proveedor"
        $isProveedor = isCliente();
        
        // Paginación
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        // Filtros — todos los parámetros GET sanitizados
        $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
        $search     = $_GET['search'] ?? '';
        $idCliente  = isset($_GET['id_cliente']) && is_numeric($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;
        $idEstado   = isset($_GET['id_estado'])  && is_numeric($_GET['id_estado'])  ? (int)$_GET['id_estado']  : 0;

        $filtros = [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'search'      => $search,
            'id_cliente'  => $idCliente,
            'id_estado'   => $idEstado,
        ];

        // 1. Notificaciones
        $notificaciones = LogisticaModel::obtenerNotificacionesCliente($userId, 10, $isProveedor);
        
        // 2. Historial paginado (tab "En Proceso" excluye finales)
        $historial = LogisticaModel::obtenerHistorialCliente($userId, $filtros, $isProveedor, $perPage, $offset, true);
        
        // 3. Contar total para paginación
        $totalPedidos = LogisticaModel::contarPedidos($userId, $filtros, $isProveedor, true);
        $totalPages   = max(1, ceil($totalPedidos / $perPage));

        // 4. Datos para dropdowns
        $estados  = LogisticaModel::obtenerEstados();
        $clientes = LogisticaModel::obtenerClientesDelProveedor($userId, $isProveedor);

        return [
            'notificaciones' => $notificaciones,
            'historial'      => $historial,
            'estados'        => $estados,
            'clientes'       => $clientes,
            'filtros'        => $filtros,
            'pagination'     => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $totalPedidos,
                'total_pages'  => $totalPages,
            ],
        ];
    }

    /*
     * Exportar pedidos a Excel con los mismos filtros del dashboard
     */
    public function exportarExcel() {
        require_once "modelo/logistica.php";
        require_once __DIR__ . '/../utils/permissions.php';
        require_once __DIR__ . '/../vendor/autoload.php';

        $userId      = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $isProveedor = isCliente();

        // Sanitizar filtros
        $filtros = [
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'search'      => $_GET['search'] ?? '',
            'id_cliente'  => isset($_GET['id_cliente']) && is_numeric($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0,
            'id_estado'   => isset($_GET['id_estado'])  && is_numeric($_GET['id_estado'])  ? (int)$_GET['id_estado']  : 0,
        ];

        // Obtener TODOS los pedidos (sin paginación) - límite de seguridad 10 000
        $pedidos = LogisticaModel::obtenerHistorialCliente($userId, $filtros, $isProveedor, 10001, 0, false);

        if (count($pedidos) > 10000) {
            // Excede límite seguro — mostrar mensaje
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'El resultado excede 10,000 filas. Aplica más filtros para reducir el rango.']);
            exit;
        }

        // Crear spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pedidos');

        // Encabezados
        $headers = [
            'A1' => 'Núm. Orden',
            'B1' => 'Fecha Ingreso',
            'C1' => 'Destinatario',
            'D1' => 'Teléfono',
            'E1' => 'Dirección',
            'F1' => 'Zona',
            'G1' => 'Código Postal',
            'H1' => 'País',
            'I1' => 'Departamento',
            'J1' => 'Municipio',
            'K1' => 'Barrio',
            'L1' => 'Estado',
            'M1' => 'Total',
            'N1' => 'Moneda',
            'O1' => 'Cliente',
            'P1' => 'Proveedor',
        ];

        $boldStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2'],
            ],
        ];

        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
            $sheet->getStyle($cell)->applyFromArray($boldStyle);
        }

        // Datos
        $row = 2;
        foreach ($pedidos as $p) {
            $fechaFmt = !empty($p['fecha_ingreso']) ? date('d/m/Y', strtotime($p['fecha_ingreso'])) : '';
            $sheet->setCellValue("A{$row}", $p['numero_orden']     ?? '');
            $sheet->setCellValue("B{$row}", $fechaFmt);
            $sheet->setCellValue("C{$row}", $p['destinatario']     ?? '');
            $sheet->setCellValue("D{$row}", $p['telefono']         ?? '');
            $sheet->setCellValue("E{$row}", $p['direccion']        ?? '');
            $sheet->setCellValue("F{$row}", $p['zona']             ?? '');
            $sheet->setCellValue("G{$row}", $p['codigo_postal']    ?? '');
            $sheet->setCellValue("H{$row}", $p['nombre_pais']      ?? '');
            $sheet->setCellValue("I{$row}", $p['nombre_departamento'] ?? '');
            $sheet->setCellValue("J{$row}", $p['nombre_municipio'] ?? '');
            $sheet->setCellValue("K{$row}", $p['nombre_barrio']    ?? '');
            $sheet->setCellValue("L{$row}", $p['estado']           ?? '');
            $sheet->setCellValue("M{$row}", $p['precio_total_local'] ?? 0);
            $sheet->setCellValue("N{$row}", $p['moneda']           ?? '');
            $sheet->setCellValue("O{$row}", $p['nombre_cliente']   ?? '');
            $sheet->setCellValue("P{$row}", $p['nombre_proveedor'] ?? '');
            $row++;
        }

        // Auto-size columnas
        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Nombre del archivo
        $timestamp = date('Ymd_Hi');
        $filename  = "pedidos_{$timestamp}.xlsx";

        // Headers HTTP para descarga
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /*
     * Obtener datos de un pedido para ver detalle (Cliente Logística)
     */
    public function obtenerDatosPedido($id) {
        require_once "modelo/logistica.php";
        require_once __DIR__ . '/../utils/permissions.php';
        
        $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $id     = (int)$id;
        $isProveedor = isCliente();

        require_once "modelo/pedido.php";
        $pedido = PedidosModel::obtenerPedidoPorId($id);

        if (!$pedido) return null;
        
        $hasAccess = $isProveedor
            ? ($pedido['id_proveedor'] == $userId)
            : ($pedido['id_cliente']   == $userId);
        
        if (!$hasAccess) return null;

        $historialCambios = LogisticaModel::obtenerHistorialCambiosPedido($id);
        $estados          = LogisticaModel::obtenerEstados();

        return [
            'pedido'   => $pedido,
            'historial' => array_reverse($historialCambios),
            'estados'  => $estados,
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

        $userId      = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $id          = (int)$id;
        $isProveedor = isCliente();

        $pedido = PedidosModel::obtenerPedidoPorId($id);
        if (!$pedido) {
            header('Location: ' . RUTA_URL . 'logistica/dashboard');
            exit;
        }
        
        $hasAccess = $isProveedor
            ? ($pedido['id_proveedor'] == $userId)
            : ($pedido['id_cliente']   == $userId);
        
        if (!$hasAccess) {
            header('Location: ' . RUTA_URL . 'logistica/dashboard');
            exit;
        }

        $nuevoEstado  = $_POST['estado']       ?? '';
        $observaciones = $_POST['observaciones'] ?? '';

        $success = false;
        if (!empty($nuevoEstado)) {
            $success = LogisticaModel::actualizarEstado($id, $nuevoEstado, $observaciones, $userId);
        }

        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Estado actualizado correctamente' : 'Error al actualizar el estado',
            ]);
            exit;
        }

        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dashboard') !== false) {
             header('Location: ' . RUTA_URL . 'logistica/dashboard?msg=actualizado');
        } else {
             header('Location: ' . RUTA_URL . 'logistica/ver/' . $id);
        }
        exit;
    }
}
