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

        return [
            'notificaciones'    => $notificaciones,
            'historial'         => $historial,
            'historialCompleto' => $historialCompleto,
            'estados'           => $estados,
            'clientes'          => $clientes,
            'filtros'           => $filtros,
            'filtrosHistorial'  => $filtrosHistorial,
            'pagination'        => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $totalPedidos,
                'total_pages'  => $totalPages,
            ],
            'paginationH'       => [
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
            'Q1' => 'Productos',
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
            $fechaFmt  = !empty($p['fecha_ingreso']) ? date('d/m/Y', strtotime($p['fecha_ingreso'])) : '';
            $productos = $productosPorPedido[(int)$p['id']] ?? '';
            $sheet->setCellValue("A{$row}", $p['numero_orden']        ?? '');
            $sheet->setCellValue("B{$row}", $fechaFmt);
            $sheet->setCellValue("C{$row}", $p['destinatario']        ?? '');
            $sheet->setCellValue("D{$row}", $p['telefono']            ?? '');
            $sheet->setCellValue("E{$row}", $p['direccion']           ?? '');
            $sheet->setCellValue("F{$row}", $p['zona']                ?? '');
            $sheet->setCellValue("G{$row}", $p['codigo_postal']       ?? '');
            $sheet->setCellValue("H{$row}", $p['nombre_pais']         ?? '');
            $sheet->setCellValue("I{$row}", $p['nombre_departamento'] ?? '');
            $sheet->setCellValue("J{$row}", $p['nombre_municipio']    ?? '');
            $sheet->setCellValue("K{$row}", $p['nombre_barrio']       ?? '');
            $sheet->setCellValue("L{$row}", $p['estado']              ?? '');
            $sheet->setCellValue("M{$row}", $p['precio_total_local']  ?? 0);
            $sheet->setCellValue("N{$row}", $p['moneda']              ?? '');
            $sheet->setCellValue("O{$row}", $p['nombre_cliente']      ?? '');
            $sheet->setCellValue("P{$row}", $p['nombre_proveedor']    ?? '');
            $sheet->setCellValue("Q{$row}", $productos);
            $row++;
        }

        // Auto-size columnas A–Q
        foreach (range('A', 'Q') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Limitar ancho máximo de la columna Productos para que no se estire demasiado
        $sheet->getColumnDimension('Q')->setAutoSize(false);
        $sheet->getColumnDimension('Q')->setWidth(60);

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
        fputcsv($out, ['numero_orden', 'estado_actual', 'estado', 'comentario', 'motivo']);

        foreach ($pedidos as $p) {
            fputcsv($out, [
                $p['numero_orden']  ?? '',
                $p['estado']        ?? '',   // referencia — NO se sube de vuelta
                '',                           // <-- usuario edita aquí el nuevo estado
                '',                           // <-- comentario opcional
                '',                           // <-- motivo opcional
            ]);
        }

        fclose($out);
        exit;
    }
}
