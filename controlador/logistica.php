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
        
        // Paginación — tab "En Proceso"
        $page    = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        // Paginación — tab "Historial Completo" (parámetro page_h para no conflictuar)
        $pageH      = isset($_GET['page_h']) && is_numeric($_GET['page_h']) ? max(1, (int)$_GET['page_h']) : 1;
        $perPageH   = 20;
        $offsetH    = ($pageH - 1) * $perPageH;

        // Filtros — todos los parámetros GET sanitizados
        // Tab "En Proceso": defaults a últimos 30 días
        $fechaDesde = isset($_GET['fecha_desde']) && $_GET['fecha_desde'] !== ''
            ? $_GET['fecha_desde']
            : date('Y-m-d', strtotime('-30 days'));
        $fechaHasta = isset($_GET['fecha_hasta']) && $_GET['fecha_hasta'] !== ''
            ? $_GET['fecha_hasta']
            : date('Y-m-d');
        $search    = trim($_GET['search']    ?? '');
        $idCliente = isset($_GET['id_cliente']) && is_numeric($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;
        $idEstado  = isset($_GET['id_estado'])  && is_numeric($_GET['id_estado'])  ? (int)$_GET['id_estado']  : 0;

        $filtros = [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'search'      => $search,
            'id_cliente'  => $idCliente,
            'id_estado'   => $idEstado,
        ];
        // Tab "Historial Completo": sin default de fechas — muestra TODOS los pedidos
        $filtrosHistorial = [
            'fecha_desde' => isset($_GET['fecha_desde']) && $_GET['fecha_desde'] !== '' ? $_GET['fecha_desde'] : '',
            'fecha_hasta' => isset($_GET['fecha_hasta']) && $_GET['fecha_hasta'] !== '' ? $_GET['fecha_hasta'] : '',
            'search'      => $search,
            'id_cliente'  => $idCliente,
            'id_estado'   => $idEstado,
        ];

        // 1. Notificaciones
        $notificaciones = LogisticaModel::obtenerNotificacionesCliente($userId, 10, $isProveedor);
        
        // 2. Pedidos activos paginados (tab "En Proceso" — excluye estados finales)
        $historial    = LogisticaModel::obtenerHistorialCliente($userId, $filtros, $isProveedor, $perPage, $offset, true);
        $totalPedidos = LogisticaModel::contarPedidos($userId, $filtros, $isProveedor, true);
        $totalPages   = max(1, ceil($totalPedidos / $perPage));

        // 3. Historial completo: TODOS los estados, con paginación independiente
        $historialCompleto     = LogisticaModel::obtenerHistorialCliente($userId, $filtrosHistorial, $isProveedor, $perPageH, $offsetH, false);
        $totalHistorial        = LogisticaModel::contarPedidos($userId, $filtrosHistorial, $isProveedor, false);
        $totalPagesH           = max(1, ceil($totalHistorial / $perPageH));

        // 4. Datos para dropdowns
        $estados  = LogisticaModel::obtenerEstados();
        $clientes = LogisticaModel::obtenerClientesDelProveedor($userId, $isProveedor);

        // 5. BI: Efectividad de proveedores de mensajería (filtrado por este cliente)
        // NOTA: $isProveedor = isCliente() tiene naming legacy confuso.
        // isCliente() devuelve TRUE para ROL_CLIENTE (NutraTrade = quien crea pedidos = id_cliente en pedidos)
        // isCliente() devuelve FALSE para ROL_PROVEEDOR (quien distribuye = id_proveedor en pedidos)
        // Para el BI de proveedores mensajería:
        //   - Si el usuario es ROL_CLIENTE ($isProveedor=true): filtrar por id_cliente = $userId
        //   - Si el usuario es ROL_PROVEEDOR ($isProveedor=false): no aplica filtro de cliente en BI
        $clienteIdParaBI = $isProveedor ? (int)$userId : null;
        $fechaBIDesde    = date('Y-m-01');
        $fechaBIHasta    = date('Y-m-t');
        $proveedoresMensajeriaBI = [];
        if ($clienteIdParaBI) {
            require_once 'modelo/pedido.php';
            $proveedoresMensajeriaBI = PedidosModel::obtenerProveedoresMensajeriaBI(
                $fechaBIDesde,
                $fechaBIHasta,
                $clienteIdParaBI
            );
        }

        // Filtros tab "Liquidados"
        $liqDesde   = isset($_GET['liq_desde']) && $_GET['liq_desde'] !== '' ? $_GET['liq_desde'] : date('Y-m-01');
        $liqHasta   = isset($_GET['liq_hasta']) && $_GET['liq_hasta'] !== '' ? $_GET['liq_hasta'] : date('Y-m-t');
        $liqSearch  = trim($_GET['liq_search'] ?? '');
        $filtrosLiq = [
            'liq_desde'  => $liqDesde,
            'liq_hasta'  => $liqHasta,
            'search'     => $liqSearch,
            'id_cliente' => $idCliente,
        ];

        $liquidadosData = LogisticaModel::obtenerLiquidados($userId, $isProveedor, $filtrosLiq, 200, 0);

        return [
            'notificaciones'         => $notificaciones,
            'historial'              => $historial,
            'historialCompleto'      => $historialCompleto,
            'estados'                => $estados,
            'clientes'               => $clientes,
            'filtros'                => $filtros,
            'filtrosHistorial'       => $filtrosHistorial,
            'filtrosLiq'             => $filtrosLiq,
            'liquidados'             => $liquidadosData['rows'],
            'liquidadosTotal'        => $liquidadosData['total'],
            'liquidadosSuma'         => $liquidadosData['suma'],
            'proveedoresMensajeriaBI' => $proveedoresMensajeriaBI,
            'pagination'             => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $totalPedidos,
                'total_pages'  => $totalPages,
            ],
            'paginationH'            => [
                'current_page' => $pageH,
                'per_page'     => $perPageH,
                'total'        => $totalHistorial,
                'total_pages'  => $totalPagesH,
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
        $tab = $_GET['tab'] ?? 'all';
        $soloActivos = ($tab === 'pedidos'); // Tab "En Proceso" → solo estados 1 y 2

        $filtros = [
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'search'      => $_GET['search'] ?? '',
            'id_cliente'  => isset($_GET['id_cliente']) && is_numeric($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0,
            // Tab "En Proceso": ignorar id_estado del GET; el backend ya filtra a IDs 1 y 2 con $soloActivos
            'id_estado'   => $soloActivos ? 0 : (isset($_GET['id_estado']) && is_numeric($_GET['id_estado']) ? (int)$_GET['id_estado'] : 0),
        ];

        // Obtener TODOS los pedidos (sin paginación) - límite de seguridad 10 000
        $pedidos = LogisticaModel::obtenerHistorialCliente($userId, $filtros, $isProveedor, 10001, 0, $soloActivos);

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
            'F1' => 'Comentario',
            'G1' => 'Zona',
            'H1' => 'Código Postal',
            'I1' => 'País',
            'J1' => 'Departamento',
            'K1' => 'Municipio',
            'L1' => 'Barrio',
            'M1' => 'Estado',
            'N1' => 'Total',
            'O1' => 'Moneda',
            'P1' => 'Cliente',
            'Q1' => 'Proveedor',
            'R1' => 'Productos',
            'S1' => 'Fecha Entrega / Reprogramación',
            'T1' => 'Fecha Liquidación',
        ];

        // Obtener productos de todos los pedidos en una sola query batch
        $pedidoIds = array_column($pedidos, 'id');
        $productosPorPedido = LogisticaModel::obtenerProductosPorPedidos(array_map('intval', $pedidoIds));

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
            $fechaFmt       = !empty($p['fecha_ingreso'])     ? date('d/m/Y', strtotime($p['fecha_ingreso']))     : '';
            $fechaEntrega   = !empty($p['fecha_entrega'])     ? date('d/m/Y', strtotime($p['fecha_entrega']))     : '';
            $fechaLiq       = !empty($p['fecha_liquidacion']) ? date('d/m/Y', strtotime($p['fecha_liquidacion'])) : '';
            $productos = $productosPorPedido[(int)$p['id']] ?? '';
            $sheet->setCellValue("A{$row}", $p['numero_orden']        ?? '');
            $sheet->setCellValue("B{$row}", $fechaFmt);
            $sheet->setCellValue("C{$row}", $p['destinatario']        ?? '');
            $sheet->setCellValue("D{$row}", $p['telefono']            ?? '');
            $sheet->setCellValue("E{$row}", $p['direccion']           ?? '');
            $sheet->setCellValue("F{$row}", $p['comentario']          ?? '');
            $sheet->setCellValue("G{$row}", $p['zona']                ?? '');
            $sheet->setCellValue("H{$row}", $p['codigo_postal']       ?? '');
            $sheet->setCellValue("I{$row}", $p['nombre_pais']         ?? '');
            $sheet->setCellValue("J{$row}", $p['nombre_departamento'] ?? '');
            $sheet->setCellValue("K{$row}", $p['nombre_municipio']    ?? '');
            $sheet->setCellValue("L{$row}", $p['nombre_barrio']       ?? '');
            $sheet->setCellValue("M{$row}", $p['estado']              ?? '');
            $sheet->setCellValue("N{$row}", $p['precio_total_local']  ?? 0);
            $sheet->setCellValue("O{$row}", $p['moneda']              ?? '');
            $sheet->setCellValue("P{$row}", $p['nombre_cliente']      ?? '');
            $sheet->setCellValue("Q{$row}", $p['nombre_proveedor']    ?? '');
            $sheet->setCellValue("R{$row}", $productos);
            $sheet->setCellValue("S{$row}", $fechaEntrega);
            $sheet->setCellValue("T{$row}", $fechaLiq);
            $row++;
        }

        // Auto-size columnas A–T
        foreach (range('A', 'T') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Limitar ancho máximo de columnas de texto largo
        $sheet->getColumnDimension('E')->setAutoSize(false);
        $sheet->getColumnDimension('E')->setWidth(45); // Dirección
        $sheet->getColumnDimension('F')->setAutoSize(false);
        $sheet->getColumnDimension('F')->setWidth(45); // Comentario
        $sheet->getColumnDimension('R')->setAutoSize(false);
        $sheet->getColumnDimension('R')->setWidth(60); // Productos

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

        $nuevoEstado      = $_POST['estado']             ?? '';
        $observaciones    = $_POST['observaciones']      ?? '';
        $fechaEntrega     = $_POST['fecha_entrega']      ?? null;
        $fechaLiquidacion = $_POST['fecha_liquidacion']  ?? null;

        $success = false;
        if (!empty($nuevoEstado)) {
            $success = LogisticaModel::actualizarEstado($id, $nuevoEstado, $observaciones, $userId, $fechaEntrega, $fechaLiquidacion);
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

    // ─────────────────────────────────────────────────────────────────────────
    // Bulk update — preview
    // ─────────────────────────────────────────────────────────────────────────

    public function bulkPreview(): void
    {
        ob_start();
        header('Content-Type: application/json');

        $userId      = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $isProveedor = isCliente(); // ROL_CLIENTE = proveedor logístico

        // Solo Cliente (proveedor) o Admin
        if (!$isProveedor && !isSuperAdmin()) {
            ob_clean();
            echo json_encode(['ok' => false, 'error' => 'Sin permiso para esta operación.']);
            exit;
        }

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            ob_clean();
            echo json_encode(['ok' => false, 'error' => 'No se recibió ningún archivo.']);
            exit;
        }

        require_once __DIR__ . '/../utils/BulkParser.php';
        require_once __DIR__ . '/../modelo/logistica.php';

        try {
            $parsed = BulkParser::parseFile($_FILES['archivo']);
        } catch (RuntimeException $e) {
            ob_clean();
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            exit;
        }

        // Validar encabezados
        $headerError = BulkParser::validateHeaders($parsed['headers']);
        if ($headerError) {
            ob_clean();
            echo json_encode(['ok' => false, 'error' => $headerError]);
            exit;
        }

        if (empty($parsed['rows'])) {
            ob_clean();
            echo json_encode(['ok' => false, 'error' => 'El archivo no contiene filas de datos.']);
            exit;
        }

        $preview = LogisticaModel::bulkPreview($parsed['rows'], $userId, $isProveedor);

        // Guardar las filas validadas en sesión para el commit
        $jobId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $_SESSION['bulk_job_' . $jobId] = [
            'rows'    => $preview['rows_validadas'],
            'user_id' => $userId,
            'archivo' => $_FILES['archivo']['name'] ?? 'bulk',
            'ts'      => time(),
        ];

        ob_clean();
        echo json_encode([
            'ok'           => true,
            'job_id'       => $jobId,
            'summary'      => $preview['summary'],
            'errores'      => $preview['errores'],
            'advertencias' => $preview['advertencias'],
            'preview_rows' => array_slice($preview['rows_validadas'], 0, 30),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bulk update — commit
    // ─────────────────────────────────────────────────────────────────────────

    public function bulkCommit(): void
    {
        ob_start();
        header('Content-Type: application/json');

        $userId      = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $isProveedor = isCliente();

        if (!$isProveedor && !isSuperAdmin()) {
            ob_clean();
            echo json_encode(['ok' => false, 'error' => 'Sin permiso para esta operación.']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $jobId = trim($input['job_id'] ?? '');

        if (empty($jobId) || !isset($_SESSION['bulk_job_' . $jobId])) {
            ob_clean();
            echo json_encode(['ok' => false, 'error' => 'Job no encontrado o expirado. Suba el archivo nuevamente.']);
            exit;
        }

        $job  = $_SESSION['bulk_job_' . $jobId];

        // Expirar job tras 30 minutos
        if (time() - ($job['ts'] ?? 0) > 1800) {
            unset($_SESSION['bulk_job_' . $jobId]);
            ob_clean();
            echo json_encode(['ok' => false, 'error' => 'La sesión expiró. Suba el archivo nuevamente.']);
            exit;
        }

        // Verificar que el job pertenece al usuario actual
        if ((int)($job['user_id'] ?? 0) !== (int)$userId) {
            ob_clean();
            echo json_encode(['ok' => false, 'error' => 'Job no válido para este usuario.']);
            exit;
        }

        require_once __DIR__ . '/../modelo/logistica.php';

        $result = LogisticaModel::bulkCommit($job['rows'], $userId, $job['archivo'] ?? 'bulk');

        // Limpiar sesión
        unset($_SESSION['bulk_job_' . $jobId]);

        ob_clean();
        echo json_encode([
            'ok'      => true,
            'summary' => $result,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Exportar plantilla CSV pre-rellena con pedidos filtrados (para bulk update)
    // ─────────────────────────────────────────────────────────────────────────
    /**
     * GET logistica/plantilla_csv?tab=...&fecha_desde=...&fecha_hasta=...&id_cliente=...&id_estado=...&search=...
     * Descarga un CSV con los pedidos filtrados listos para editar:
     *   numero_orden | destinatario | estado_actual | estado | comentario | motivo
     * Las columnas "estado" y "comentario" van vacías para que el usuario las complete y
     * suba el archivo al bulk-update.
     */
    /*
     * Exportar pedidos LIQUIDADOS a Excel con los filtros del tab Liquidados
     */
    public function exportarLiquidadosExcel(): void
    {
        require_once "modelo/logistica.php";
        require_once __DIR__ . '/../utils/permissions.php';
        require_once __DIR__ . '/../vendor/autoload.php';

        $userId      = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $isProveedor = isCliente();

        // Sanitizar filtros (idéntico al controlador dashboard)
        $liqDesde  = isset($_GET['liq_desde']) && $_GET['liq_desde'] !== '' ? $_GET['liq_desde'] : '';
        $liqHasta  = isset($_GET['liq_hasta']) && $_GET['liq_hasta'] !== '' ? $_GET['liq_hasta'] : '';
        $liqSearch = trim($_GET['liq_search'] ?? '');
        $idCliente = isset($_GET['id_cliente']) && is_numeric($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;

        $filtros = [
            'liq_desde'  => $liqDesde,
            'liq_hasta'  => $liqHasta,
            'search'     => $liqSearch,
            'id_cliente' => $idCliente,
        ];

        // Obtener TODOS los liquidados (sin paginación) — límite seguro 10 000
        $result = LogisticaModel::obtenerLiquidados($userId, $isProveedor, $filtros, 10001, 0);
        $rows   = $result['rows'];

        if (count($rows) > 10000) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'El resultado excede 10,000 filas. Aplica más filtros.']);
            exit;
        }

        // Crear spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Liquidados');

        // Estilo encabezado
        $boldStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D5E8D4'],   // verde suave
            ],
        ];

        // Encabezados
        $headers = [
            'A1' => 'Núm. Orden',
            'B1' => 'Destinatario',
            'C1' => 'Teléfono',
            'D1' => 'Fecha Ingreso',
            'E1' => 'Fecha Liquidación',
            'F1' => 'Total',
            'G1' => 'Moneda',
            'H1' => 'Cliente',
            'I1' => 'Proveedor',
        ];

        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
            $sheet->getStyle($cell)->applyFromArray($boldStyle);
        }

        // Datos
        $row = 2;
        foreach ($rows as $liq) {
            $fechaIngreso     = !empty($liq['fecha_ingreso'])     ? date('d/m/Y', strtotime($liq['fecha_ingreso']))     : '';
            $fechaLiquidacion = !empty($liq['fecha_liquidacion']) ? date('d/m/Y', strtotime($liq['fecha_liquidacion'])) : '';

            $sheet->setCellValue("A{$row}", $liq['numero_orden']       ?? '');
            $sheet->setCellValue("B{$row}", $liq['destinatario']       ?? '');
            $sheet->setCellValue("C{$row}", $liq['telefono']           ?? '');
            $sheet->setCellValue("D{$row}", $fechaIngreso);
            $sheet->setCellValue("E{$row}", $fechaLiquidacion);
            $sheet->setCellValue("F{$row}", $liq['precio_total_local'] ?? 0);
            $sheet->setCellValue("G{$row}", $liq['moneda']             ?? '');
            $sheet->setCellValue("H{$row}", $liq['nombre_cliente']     ?? '');
            $sheet->setCellValue("I{$row}", $liq['nombre_proveedor']   ?? '');
            $row++;
        }

        // Fila de total al final
        if (!empty($rows)) {
            $totalRow = $row;
            $sheet->setCellValue("E{$totalRow}", 'TOTAL:');
            $sheet->setCellValue("F{$totalRow}", $result['suma']);
            $totalStyle = ['font' => ['bold' => true], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D5E8D4']]];
            $sheet->getStyle("A{$totalRow}:I{$totalRow}")->applyFromArray($totalStyle);
        }

        // Auto-size columnas A–I
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Nombre del archivo con rango de fechas
        $desde    = $liqDesde  ? str_replace('-', '', $liqDesde)  : 'inicio';
        $hasta    = $liqHasta  ? str_replace('-', '', $liqHasta)  : date('Ymd');
        $filename = "liquidados_{$desde}_{$hasta}.xlsx";

        if (ob_get_length()) ob_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function exportarPlantillaCSV(): void
    {
        require_once 'modelo/logistica.php';
        require_once __DIR__ . '/../utils/permissions.php';

        $userId      = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $isProveedor = isCliente();

        // Sólo cliente logístico o admin
        if (!$isProveedor && !isSuperAdmin()) {
            http_response_code(403);
            echo 'Sin permiso.';
            exit;
        }

        // Sanitizar filtros (idéntico a exportarExcel)
        $filtros = [
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'search'      => $_GET['search']      ?? '',
            'id_cliente'  => isset($_GET['id_cliente']) && is_numeric($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0,
            'id_estado'   => isset($_GET['id_estado'])  && is_numeric($_GET['id_estado'])  ? (int)$_GET['id_estado']  : 0,
        ];

        // Decidir si el tab activo excluye estados finales o no
        $soloActivos = (($_GET['tab'] ?? 'all') === 'pedidos');

        // Obtener TODOS los pedidos que coincidan (sin paginación), límite 10 000
        $pedidos = LogisticaModel::obtenerHistorialCliente($userId, $filtros, $isProveedor, 10001, 0, $soloActivos);

        if (count($pedidos) > 10000) {
            http_response_code(400);
            echo 'El resultado excede 10,000 filas. Aplica más filtros.';
            exit;
        }

        // Construir CSV en memoria
        $timestamp = date('Ymd_Hi');
        $filename  = "plantilla_bulk_{$timestamp}.csv";

        if (ob_get_length()) ob_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $out = fopen('php://output', 'w');
        // BOM para que Excel abra bien en Windows con UTF-8
        fputs($out, "\xEF\xBB\xBF");

        // Encabezado: columnas reconocidas por BulkParser + referencias de solo lectura
        fputcsv($out, ['numero_orden', 'estado_actual', 'estado', 'comentario', 'motivo', 'fecha_entrega', 'fecha_liquidacion']);

        foreach ($pedidos as $p) {
            fputcsv($out, [
                $p['numero_orden']  ?? '',
                $p['estado']        ?? '',   // referencia — NO se sube de vuelta
                '',                           // <-- nuevo estado
                '',                           // <-- comentario opcional
                '',                           // <-- motivo opcional
                '',                           // <-- fecha_entrega (solo si estado = Reprogramado)
                '',                           // <-- fecha_liquidacion (solo si estado = Entregado – liquidado)
            ]);
        }

        fclose($out);
        exit;
    }
}
