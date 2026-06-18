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
        // Tab "En Proceso": SIN default de fecha → muestra TODOS los pedidos activos
        // (sin importar mes de ingreso; el filtro de estados 1, 2 y 4 ya los acota)
        $fechaDesde = isset($_GET['fecha_desde']) && $_GET['fecha_desde'] !== ''
            ? $_GET['fecha_desde']
            : '';
        $fechaHasta = isset($_GET['fecha_hasta']) && $_GET['fecha_hasta'] !== ''
            ? $_GET['fecha_hasta']
            : '';
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
        // Tab "Historial Completo": defaults al 1° del mes actual hasta hoy
        $filtrosHistorial = [
            'fecha_desde' => isset($_GET['fecha_desde']) && $_GET['fecha_desde'] !== '' ? $_GET['fecha_desde'] : date('Y-m-01'),
            'fecha_hasta' => isset($_GET['fecha_hasta']) && $_GET['fecha_hasta'] !== '' ? $_GET['fecha_hasta'] : date('Y-m-d'),
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
        require_once __DIR__ . '/../helpers/helpers.php';

        $userId      = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $isProveedor = isCliente();

        // Sanitizar filtros
        $tab = $_GET['tab'] ?? 'all';
        $soloActivos = ($tab === 'pedidos'); // Tab "En Proceso" → estados 1, 2 y 4 (En bodega, En ruta, Reprogramado)

        $filtros = [
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
            'search'      => $_GET['search'] ?? '',
            'id_cliente'  => isset($_GET['id_cliente']) && is_numeric($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0,
            // Tab "En Proceso": respetar id_estado del GET (afecta estados 1, 2 y 4 según el IN del modelo)
            'id_estado'   => isset($_GET['id_estado']) && is_numeric($_GET['id_estado']) ? (int)$_GET['id_estado'] : 0,
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

        // Obtener productos detallados (array estructurado) para columnas dinámicas
        $pedidoIds = array_column($pedidos, 'id');
        $productosPorPedidoDetalle = LogisticaModel::obtenerProductosPorPedidosDetallado(array_map('intval', $pedidoIds));

        // Máximo de productos en cualquier pedido del lote
        $maxProductos = 1;
        foreach ($productosPorPedidoDetalle as $prods) {
            if (count($prods) > $maxProductos) $maxProductos = count($prods);
        }

        // Encabezados fijos A–X
        $headersFixed = [
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
            'M1' => 'CP (texto libre)',
            'N1' => 'Depto. (texto libre)',
            'O1' => 'Municipio (texto libre)',
            'P1' => 'Barrio (texto libre)',
            'Q1' => 'Entre Calles',
            'R1' => 'Estado',
            'S1' => 'Fecha Entrega / Reprogramación',
            'T1' => 'Fecha Liquidación',
            'U1' => 'Total',
            'V1' => 'Moneda',
            'W1' => 'Cliente',
            'X1' => 'Proveedor',
        ];

        $boldStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
        ];
        $boldStyleProd = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
        ];

        // Escribir encabezados fijos
        foreach ($headersFixed as $cell => $label) {
            $sheet->setCellValue($cell, $label);
            $sheet->getStyle($cell)->applyFromArray($boldStyle);
        }

        // Encabezados dinámicos de productos a partir de columna Y (índice 25)
        // M–Q reservadas para campos opcionales de dirección especial
        $colInicioProd = 25; // Y = 25 en PhpSpreadsheet (A=1)
        for ($i = 0; $i < $maxProductos; $i++) {
            $num         = $i + 1;
            $colNombre   = $colInicioProd + ($i * 2);
            $colCantidad = $colInicioProd + ($i * 2) + 1;
            $cellN = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNombre)   . '1';
            $cellC = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCantidad) . '1';
            $sheet->setCellValue($cellN, "Producto {$num}");
            $sheet->setCellValue($cellC, "Cantidad {$num}");
            $sheet->getStyle($cellN)->applyFromArray($boldStyleProd);
            $sheet->getStyle($cellC)->applyFromArray($boldStyleProd);
        }

        // Preparar conexión y query para fallback de CP (solo se usa si datos directos faltan)
        require_once __DIR__ . '/../services/AddressService.php';
        $dbExcel = (new Conexion())->conectar();
        $cpSql = "
            SELECT d.nombre AS nom_depto,
                   mu.nombre AS nom_muni,
                   b.nombre AS nom_barrio
            FROM codigos_postales cp
            LEFT JOIN departamentos d  ON d.id  = cp.id_departamento
            LEFT JOIN municipios    mu ON mu.id = cp.id_municipio
            LEFT JOIN barrios       b  ON b.id  = cp.id_barrio
            WHERE cp.codigo_postal = :cp
              AND cp.id_departamento IS NOT NULL
            LIMIT 1
        ";

        // Estilo para datos inferidos por fallback: fondo amarillo suave
        $styleInferido = [
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFF2CC'],
            ],
        ];

        // Datos
        $fila = 2;
        foreach ($pedidos as $p) {
            $fechaFmt     = localDate($p['fecha_ingreso'] ?? null, 'd/m/Y', '');
            $fechaEntrega = localDate($p['fecha_entrega'] ?? null, 'd/m/Y', '');
            $fechaLiq     = localDate($p['fecha_liquidacion'] ?? null, 'd/m/Y', '');

            // El modelo ya resuelve depto/muni via COALESCE(FK, cp_hom) en SQL.
            // Solo queda como fallback PHP el caso residual: CP sin prefijo de país.
            $nomPais      = $p['nombre_pais']         ?? '';
            $nomDepto     = $p['nombre_departamento'] ?? '';
            $nomMuni      = $p['nombre_municipio']    ?? '';
            $nomBarrio    = $p['nombre_barrio']       ?? '';
            $cpDisplay    = $p['codigo_postal']       ?? '';
            $deptoInferid = false;
            $muniInferido = false;

            // Fallback país: si id_pais no está seteado, inferir desde id_moneda
            if (!$nomPais && !empty($p['id_moneda'])) {
                try {
                    $stPais = $dbExcel->prepare("SELECT nombre FROM paises WHERE id_moneda_local = :m LIMIT 1");
                    $stPais->execute([':m' => (int)$p['id_moneda']]);
                    $nomPais = $stPais->fetchColumn() ?: '';
                } catch (Exception $e) { /* silently continue */ }
            }

            // Campos opcionales de dirección especial (para columnas T–X)
            $locationField   = trim($p['Location']          ?? '');
            $betweenStrField = trim($p['betweenStreets']     ?? '');
            $deptNameField   = trim($p['departmentName']     ?? '');
            $muniNameField   = trim($p['municipalitiesName'] ?? '');
            $postalCodeField = trim($p['postalCode']         ?? '');

            // Fallback PHP (CP homologación): solo se ejecuta si el SQL no resolvió depto/muni.
            // Nivel 0: búsqueda exacta con el CP tal como viene → dato confirmado, sin (*)
            // Nivel 1: agrega prefijo del país (CP transformado) → dato inferido, con (*)
            if ((!$nomDepto || !$nomMuni || !$nomBarrio) && !empty($p['codigo_postal'])) {
                try {
                    $cpBruto = strtoupper(trim($p['codigo_postal']));

                    // Nivel 0: búsqueda exacta — si lo encuentra, dato es confirmado (sin *)
                    $st = $dbExcel->prepare($cpSql);
                    $st->execute([':cp' => $cpBruto]);
                    $cpRow = $st->fetch(PDO::FETCH_ASSOC);
                    if ($cpRow) {
                        if (!$nomDepto  && !empty($cpRow['nom_depto']))  $nomDepto  = $cpRow['nom_depto'];
                        if (!$nomMuni   && !empty($cpRow['nom_muni']))   $nomMuni   = $cpRow['nom_muni'];
                        if (!$nomBarrio && !empty($cpRow['nom_barrio'])) $nomBarrio = $cpRow['nom_barrio'];
                    }

                    // Nivel 1: agrega prefijo del país (ej. "10110" → "CR10110")
                    // Solo si aún faltan datos y el CP no tenía ya el prefijo
                    $idPaisEfectivo = null;
                    if ((!$nomDepto || !$nomMuni || !$nomBarrio)) {
                        if (!empty($p['id_moneda'])) {
                            $stP = $dbExcel->prepare("SELECT id FROM paises WHERE id_moneda_local = :m LIMIT 1");
                            $stP->execute([':m' => (int)$p['id_moneda']]);
                            $idPaisEfectivo = (int)($stP->fetchColumn() ?: 0) ?: null;
                        }
                        if (!$idPaisEfectivo && !empty($p['id_pais'])) {
                            $idPaisEfectivo = (int)$p['id_pais'];
                        }
                        if ($idPaisEfectivo) {
                            require_once __DIR__ . '/../services/AddressService.php';
                            $cpConPrefijo = AddressService::normalizarCP($p['codigo_postal'], $idPaisEfectivo);
                            if ($cpConPrefijo !== $cpBruto) {
                                $st = $dbExcel->prepare($cpSql);
                                $st->execute([':cp' => $cpConPrefijo]);
                                $cpRow = $st->fetch(PDO::FETCH_ASSOC);
                                if ($cpRow) {
                                    if (!$nomDepto && !empty($cpRow['nom_depto'])) {
                                        $nomDepto     = $cpRow['nom_depto'] . ' (*)';  // CP transformado → inferido
                                        $deptoInferid = true;
                                    }
                                    if (!$nomMuni && !empty($cpRow['nom_muni'])) {
                                        $nomMuni      = $cpRow['nom_muni'] . ' (*)';
                                        $muniInferido = true;
                                    }
                                    if (!$nomBarrio && !empty($cpRow['nom_barrio'])) {
                                        $nomBarrio = $cpRow['nom_barrio'] . ' (*)';
                                    }
                                    $cpDisplay = $cpConPrefijo;
                                }
                            }
                        }
                    }

                    // Nivel 2: ceros a la izquierda (ej. "574" → "0574")
                    if ((!$nomDepto || !$nomMuni || !$nomBarrio) && ctype_digit($cpBruto)) {
                        $cpPadded = str_pad($cpBruto, 4, '0', STR_PAD_LEFT);
                        if ($cpPadded !== $cpBruto) {
                            $st = $dbExcel->prepare($cpSql);
                            $st->execute([':cp' => $cpPadded]);
                            $cpRow = $st->fetch(PDO::FETCH_ASSOC);
                            if ($cpRow) {
                                if (!$nomDepto && !empty($cpRow['nom_depto'])) {
                                    $nomDepto     = $cpRow['nom_depto'] . ' (*)';
                                    $deptoInferid = true;
                                }
                                if (!$nomMuni && !empty($cpRow['nom_muni'])) {
                                    $nomMuni      = $cpRow['nom_muni'] . ' (*)';
                                    $muniInferido = true;
                                }
                                if (!$nomBarrio && !empty($cpRow['nom_barrio'])) {
                                    $nomBarrio = $cpRow['nom_barrio'] . ' (*)';
                                }
                                $cpDisplay = $cpPadded;
                            }
                        }
                    }

                    // Nivel 3: prefijo + ceros a la izquierda (ej. "574" → "GT0574")
                    if ((!$nomDepto || !$nomMuni || !$nomBarrio) && ctype_digit($cpBruto) && $idPaisEfectivo) {
                        $cpPadPrefijo = AddressService::normalizarCP(str_pad($cpBruto, 4, '0', STR_PAD_LEFT), $idPaisEfectivo);
                        if ($cpPadPrefijo !== $cpBruto) {
                            $st = $dbExcel->prepare($cpSql);
                            $st->execute([':cp' => $cpPadPrefijo]);
                            $cpRow = $st->fetch(PDO::FETCH_ASSOC);
                            if ($cpRow) {
                                if (!$nomDepto && !empty($cpRow['nom_depto'])) {
                                    $nomDepto     = $cpRow['nom_depto'] . ' (*)';
                                    $deptoInferid = true;
                                }
                                if (!$nomMuni && !empty($cpRow['nom_muni'])) {
                                    $nomMuni      = $cpRow['nom_muni'] . ' (*)';
                                    $muniInferido = true;
                                }
                                if (!$nomBarrio && !empty($cpRow['nom_barrio'])) {
                                    $nomBarrio = $cpRow['nom_barrio'] . ' (*)';
                                }
                                $cpDisplay = $cpPadPrefijo;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // silently continue
                }
            }

            $sheet->setCellValue("A{$fila}", $p['numero_orden']        ?? '');
            $sheet->setCellValue("B{$fila}", $fechaFmt);
            $sheet->setCellValue("C{$fila}", $p['destinatario']        ?? '');
            $sheet->setCellValue("D{$fila}", $p['telefono']            ?? '');
            $sheet->setCellValue("E{$fila}", $p['direccion']           ?? '');
            $sheet->setCellValue("F{$fila}", $p['comentario']          ?? '');
            $sheet->setCellValue("G{$fila}", $p['zona']                ?? '');
            $sheet->setCellValue("H{$fila}", $cpDisplay);
            $sheet->setCellValue("I{$fila}", $nomPais);
            $sheet->setCellValue("J{$fila}", $nomDepto);
            $sheet->setCellValue("K{$fila}", $nomMuni);
            $sheet->setCellValue("L{$fila}", $nomBarrio);
            // Campos opcionales de dirección especial (M–Q): CP, jerarquía geográfica
            $sheet->setCellValue("M{$fila}", $postalCodeField);
            $sheet->setCellValue("N{$fila}", $deptNameField);
            $sheet->setCellValue("O{$fila}", $muniNameField);
            $sheet->setCellValue("P{$fila}", $locationField);
            $sheet->setCellValue("Q{$fila}", $betweenStrField);
            // Resto de datos del pedido (R–X)
            $sheet->setCellValue("R{$fila}", $p['estado']              ?? '');
            $sheet->setCellValue("S{$fila}", $fechaEntrega);
            $sheet->setCellValue("T{$fila}", $fechaLiq);
            $sheet->setCellValue("U{$fila}", $p['precio_total_local']  ?? 0);
            $sheet->setCellValue("V{$fila}", $p['moneda']              ?? '');
            $sheet->setCellValue("W{$fila}", $p['nombre_cliente']      ?? '');
            $sheet->setCellValue("X{$fila}", $p['nombre_proveedor']    ?? '');

            // Pintar de amarillo las celdas con valores inferidos por fallback
            if ($deptoInferid) $sheet->getStyle("J{$fila}")->applyFromArray($styleInferido);
            if ($muniInferido) $sheet->getStyle("K{$fila}")->applyFromArray($styleInferido);

            // Columnas dinámicas de productos
            $prodsDelPedido = $productosPorPedidoDetalle[(int)$p['id']] ?? [];
            for ($i = 0; $i < $maxProductos; $i++) {
                $colN = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colInicioProd + ($i * 2));
                $colC = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colInicioProd + ($i * 2) + 1);
                $sheet->setCellValue("{$colN}{$fila}", isset($prodsDelPedido[$i]) ? $prodsDelPedido[$i]['nombre']   : '');
                $sheet->setCellValue("{$colC}{$fila}", isset($prodsDelPedido[$i]) ? $prodsDelPedido[$i]['cantidad'] : '');
            }

            $fila++;
        }

        // Auto-size todas las columnas (fijas + dinámicas)
        $lastColIdx = $colInicioProd + ($maxProductos * 2) - 1;
        $lastCol    = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIdx);
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Limitar ancho de columnas de texto largo
        $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(45); // Dirección
        $sheet->getColumnDimension('F')->setAutoSize(false)->setWidth(45); // Comentario
        $sheet->getColumnDimension('M')->setAutoSize(false)->setWidth(20); // CP libre
        $sheet->getColumnDimension('N')->setAutoSize(false)->setWidth(30); // Depto. libre
        $sheet->getColumnDimension('O')->setAutoSize(false)->setWidth(30); // Municipio libre
        $sheet->getColumnDimension('P')->setAutoSize(false)->setWidth(40); // Barrio libre
        $sheet->getColumnDimension('Q')->setAutoSize(false)->setWidth(40); // Entre Calles

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
        require_once __DIR__ . '/../helpers/helpers.php';

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
            $fechaIngreso     = localDate($liq['fecha_ingreso'] ?? null, 'd/m/Y', '');
            $fechaLiquidacion = localDate($liq['fecha_liquidacion'] ?? null, 'd/m/Y', '');

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

    // ─────────────────────────────────────────────────────────────────────────
    // Centro de Notificaciones Logísticas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retorna datos para la vista de notificaciones logísticas.
     */
    public function notificaciones(): array {
        require_once 'modelo/logistica_notification.php';

        $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        if ($userId <= 0) {
            return ['notificaciones' => [], 'pendientes' => [], 'unread_count' => 0, 'pagination' => []];
        }

        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;
        $onlyUnread = isset($_GET['unread']) && $_GET['unread'] === 'true';
        $search = trim($_GET['q'] ?? '');

        $notificaciones = LogisticaNotificationModel::obtenerPorUsuario($userId, $limit, $offset, $onlyUnread, $search);
        $total          = LogisticaNotificationModel::contarTotalPorUsuario($userId, $onlyUnread, $search);
        $unreadCount    = LogisticaNotificationModel::contarNoLeidas($userId);
        $pendientes     = LogisticaNotificationModel::obtenerPendientes($userId);

        // Decodificar payload JSON en cada notificación
        foreach ($notificaciones as &$n) {
            if (isset($n['payload']) && is_string($n['payload'])) {
                $n['payload'] = json_decode($n['payload'], true) ?? [];
            }
        }
        unset($n);

        foreach ($pendientes as &$p) {
            if (isset($p['payload']) && is_string($p['payload'])) {
                $p['payload'] = json_decode($p['payload'], true) ?? [];
            }
        }
        unset($p);

        $totalPages = max(1, (int)ceil($total / $limit));

        return [
            'notificaciones' => $notificaciones,
            'pendientes'     => $pendientes,
            'unread_count'   => $unreadCount,
            'search_query'   => $search,
            'pagination'     => [
                'current_page' => $page,
                'total_pages'  => $totalPages,
                'total_items'  => $total,
                'limit'        => $limit,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HL Express: Listar, Exportar y Resolver Novedades (bulk)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Buscar un pedido local por número de orden.
     * GET logistica/buscarPedidoPorOrden?numero_orden=ORD-001
     * Retorna: { success, id, numero_orden }
     */
    public function buscarPedidoPorOrden() {
        require_once "modelo/pedido.php";
        $numeroOrden = trim($_GET['numero_orden'] ?? '');
        if (empty($numeroOrden)) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => 'Parámetro numero_orden requerido.']);
            exit;
        }
        $model  = new PedidosModel();
        // Normalizar: quitar el # inicial si lo tiene (los datos demo lo incluyen)
        $buscar  = ltrim($numeroOrden, '#');
        $pedido  = $model->obtenerPedidoPorNumero($buscar);
        if (!$pedido) {
            // Intentar también con # (por si la BD lo guarda con prefijo)
            $pedido = $model->obtenerPedidoPorNumero('#' . $buscar);
        }

        // Si no se encontró por numero_orden directo, intentar cruzando con forwarding_log.
        // HL Express puede devolver el order_number con un prefijo propio (ej. "WCO2801")
        // que no coincide con el numero_orden interno ("2801").
        // Buscamos el pedido cuyo response_payload de forwarding contenga ese order_number,
        // o bien cuyo numero_orden esté contenido al final del valor buscado.
        if (!$pedido) {
            try {
                $db = (new Conexion())->conectar();

                // Estrategia 1: buscar en forwarding_log por order_number dentro del response_payload JSON
                $stmt = $db->prepare(
                    "SELECT p.id, p.numero_orden
                     FROM forwarding_log fl
                     JOIN pedidos p ON p.id = fl.id_pedido
                     WHERE fl.status = 'success'
                       AND JSON_UNQUOTE(JSON_EXTRACT(fl.response_payload, '$.order_number')) = :on
                     LIMIT 1"
                );
                $stmt->execute([':on' => $buscar]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $pedido = $model->obtenerPedidoPorNumero($row['numero_orden']);
                }

                // Estrategia 2: si el order_number de HL Express termina en el numero_orden interno
                // (ej. "WCO2801" termina en "2801")
                if (!$pedido) {
                    $stmt2 = $db->prepare(
                        "SELECT p.id, p.numero_orden
                         FROM pedidos p
                         JOIN forwarding_log fl ON fl.id_pedido = p.id
                         WHERE fl.status = 'success'
                           AND :on LIKE CONCAT('%', p.numero_orden)
                         LIMIT 1"
                    );
                    $stmt2->execute([':on' => $buscar]);
                    $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                    if ($row2) {
                        $pedido = $model->obtenerPedidoPorNumero($row2['numero_orden']);
                    }
                }
            } catch (Exception $e) {
                error_log('[buscarPedidoPorOrden] Fallback forwarding_log error: ' . $e->getMessage());
            }
        }

        if (!$pedido) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'message' => "Pedido '{$numeroOrden}' no encontrado."]);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'id' => (int)$pedido['id'], 'numero_orden' => $pedido['numero_orden']]);
        exit;
    }


    /**
     * Listar todas las novedades activas de HL Express (paginado).
     * GET logistica/listarNovedadesHLExpress
     * Params: page, limit, is_solved, order_number, tracking_number, start_date, end_date, status_id
     */
    public function listarNovedadesHLExpress() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            exit;
        }

        require_once "modelo/forwarding.php";
        require_once __DIR__ . '/../utils/permissions.php';
        require_once __DIR__ . '/../services/providers/HLExpressProvider.php';

        try {
            $providerData = ForwardingModel::obtenerProveedorPorSlug('hlexpress');
            if (!$providerData) {
                throw new Exception("Proveedor HL Express no configurado en la plataforma.");
            }

            $credentials   = json_decode($providerData['credentials']    ?? '{}', true) ?: [];
            $defaultConfig = json_decode($providerData['default_config'] ?? '{}', true) ?: [];
            $config = array_merge($defaultConfig, [
                'auth_endpoint'  => $providerData['auth_endpoint']  ?? '/api/AccountApi',
                'order_endpoint' => $providerData['order_endpoint'] ?? '/shipments',
                'auth_method'    => $providerData['auth_method']    ?? 'api_key',
            ]);

            $hlExpress = new HLExpressProvider($providerData['base_url'], $credentials, $config);

            $filters = [];
            $filters['page']     = max(1, (int)($_GET['page']     ?? 1));
            $filters['limit']    = min(50, max(10, (int)($_GET['limit'] ?? 20)));
            if (!empty($_GET['is_solved']))       $filters['is_solved']       = $_GET['is_solved'];
            if (!empty($_GET['order_number']))    $filters['order_number']    = trim($_GET['order_number']);
            if (!empty($_GET['tracking_number'])) $filters['tracking_number'] = trim($_GET['tracking_number']);
            if (!empty($_GET['start_date']))      $filters['start_date']      = trim($_GET['start_date']);
            if (!empty($_GET['end_date']))        $filters['end_date']        = trim($_GET['end_date']);
            if (!empty($_GET['status_id']))       $filters['status_id']       = (int)$_GET['status_id'];

            // Por defecto solo mostramos no resueltas
            if (!isset($filters['is_solved'])) {
                $filters['is_solved'] = 'No';
            }

            $result = $hlExpress->getIncidentsFiltered($filters);

            header('Content-Type: application/json');
            echo json_encode(['success' => true] + $result);
            exit;

        } catch (Exception $e) {
            error_log("LogisticaController::listarNovedadesHLExpress error: " . $e->getMessage());
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Listar envíos de HL Express con filtros y paginación real.
     * GET logistica/listarEnviosHLExpress
     * Params: page, limit, status_id, tracking_number, order_number, start_date, end_date
     */
    public function listarEnviosHLExpress() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            exit;
        }

        require_once "modelo/forwarding.php";
        require_once __DIR__ . '/../utils/permissions.php';
        require_once __DIR__ . '/../services/providers/HLExpressProvider.php';

        try {
            $providerData = ForwardingModel::obtenerProveedorPorSlug('hlexpress');
            if (!$providerData) {
                throw new Exception("Proveedor HL Express no configurado en la plataforma.");
            }

            $credentials   = json_decode($providerData['credentials']    ?? '{}', true) ?: [];
            $defaultConfig = json_decode($providerData['default_config'] ?? '{}', true) ?: [];
            $config = array_merge($defaultConfig, [
                'auth_endpoint'  => $providerData['auth_endpoint']  ?? '/api/AccountApi',
                'order_endpoint' => $providerData['order_endpoint'] ?? '/shipments',
                'auth_method'    => $providerData['auth_method']    ?? 'api_key',
            ]);

            $hlExpress = new HLExpressProvider($providerData['base_url'], $credentials, $config);

            $filters = [];
            $filters['page']  = max(1, (int)($_GET['page']  ?? 1));
            $filters['limit'] = min(50, max(10, (int)($_GET['limit'] ?? 20)));
            if (!empty($_GET['status_id']))       $filters['status_id']       = (int)$_GET['status_id'];
            if (!empty($_GET['tracking_number'])) $filters['tracking_number'] = trim($_GET['tracking_number']);
            if (!empty($_GET['order_number']))    $filters['order_number']    = trim($_GET['order_number']);
            if (!empty($_GET['start_date']))      $filters['start_date']      = trim($_GET['start_date']);
            if (!empty($_GET['end_date']))        $filters['end_date']        = trim($_GET['end_date']);

            $result = $hlExpress->getShipmentsFiltered($filters);

            header('Content-Type: application/json');
            echo json_encode(['success' => true] + $result);
            exit;

        } catch (Exception $e) {
            error_log("LogisticaController::listarEnviosHLExpress error: " . $e->getMessage());
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Exportar novedades activas de HL Express a Excel.
     * GET logistica/exportarNovedadesExcel
     */
    public function exportarNovedadesExcel() {
        require_once "modelo/forwarding.php";
        require_once __DIR__ . '/../utils/permissions.php';
        require_once __DIR__ . '/../services/providers/HLExpressProvider.php';
        require_once __DIR__ . '/../vendor/autoload.php';

        try {
            $providerData = ForwardingModel::obtenerProveedorPorSlug('hlexpress');
            if (!$providerData) throw new Exception("Proveedor HL Express no configurado.");

            $credentials   = json_decode($providerData['credentials']    ?? '{}', true) ?: [];
            $defaultConfig = json_decode($providerData['default_config'] ?? '{}', true) ?: [];
            $config = array_merge($defaultConfig, [
                'auth_endpoint'  => $providerData['auth_endpoint']  ?? '/api/AccountApi',
                'order_endpoint' => $providerData['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
                'auth_method'    => $providerData['auth_method']    ?? 'bearer_jwt',
            ]);

            $hlExpress = new HLExpressProvider($providerData['base_url'], $credentials, $config);

            // Obtener TODAS las páginas de novedades sin resolver
            $allIncidents = [];
            $page = 1;
            do {
                $result = $hlExpress->getIncidentsFiltered([
                    'is_solved' => 'No',
                    'page'      => $page,
                    'limit'     => 50,
                ]);
                $allIncidents = array_merge($allIncidents, $result['data'] ?? []);
                $lastPage = $result['last_page'] ?? 1;
                $page++;
            } while ($page <= $lastPage && $page <= 20); // máximo 20 páginas / 1000 registros

            // Crear spreadsheet
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Novedades HL Express');

            $boldStyle = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFC000']],
            ];
            $infoStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
            ];

            $headers = [
                'A1' => 'numero_orden',
                'B1' => 'tracking_number',
                'C1' => 'destinatario',
                'D1' => 'telefono',
                'E1' => 'direccion',
                'F1' => 'tipo_novedad_id',
                'G1' => 'is_solved',
                'H1' => 'fecha_novedad',
                // Columnas a rellenar por el usuario:
                'I1' => 'accion',
                'J1' => 'nueva_solucion',
                'K1' => 'nuevo_nombre',
                'L1' => 'nuevo_telefono',
                'M1' => 'nueva_direccion',
            ];

            foreach ($headers as $cell => $label) {
                $sheet->setCellValue($cell, $label);
                $col = substr($cell, 0, 1);
                // Columnas a rellenar (I-M) en azul, info (A-H) en naranja
                $sheet->getStyle($cell)->applyFromArray(ord($col) >= ord('I') ? $infoStyle : $boldStyle);
            }

            // Añadir comentario en celda I1 explicando valores válidos
            $comment = $sheet->getComment('I1');
            $comment->getText()->createTextRun('Valores válidos: reintentar | devolver');

            $row = 2;
            foreach ($allIncidents as $inc) {
                $shipment = $inc['shipment'] ?? [];
                $dest     = $shipment['shipment_destination'] ?? [];

                $sheet->setCellValue("A{$row}", $shipment['order_number']    ?? '');
                $sheet->setCellValue("B{$row}", $shipment['tracking_number'] ?? '');
                $sheet->setCellValue("C{$row}", $dest['full_name']           ?? '');
                $sheet->setCellValue("D{$row}", $dest['phone_number']        ?? '');
                $sheet->setCellValue("E{$row}", $dest['address']             ?? '');
                $sheet->setCellValue("F{$row}", $inc['incident_type']['name'] ?? ($inc['status'] ?? ''));
                $sheet->setCellValue("G{$row}", $inc['is_solved']            ?? 'No');
                $sheet->setCellValue("H{$row}", $inc['created_at']           ?? '');
                // I–M quedan vacías para que el usuario las llene
                $sheet->setCellValue("I{$row}", ''); // accion
                $sheet->setCellValue("J{$row}", ''); // nueva_solucion
                $sheet->setCellValue("K{$row}", $dest['full_name']    ?? ''); // nuevo_nombre (pre-rellenado)
                $sheet->setCellValue("L{$row}", $dest['phone_number'] ?? ''); // nuevo_telefono (pre-rellenado)
                $sheet->setCellValue("M{$row}", $dest['address']      ?? ''); // nueva_direccion (pre-rellenado)

                $row++;
            }

            // Auto-size columnas
            foreach (range('A', 'M') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(40);
            $sheet->getColumnDimension('J')->setAutoSize(false)->setWidth(35);
            $sheet->getColumnDimension('M')->setAutoSize(false)->setWidth(40);

            // Fijar la primera fila como encabezado
            $sheet->freezePane('A2');

            // ── Hoja 2: Instrucciones de uso ─────────────────────────────────────
            $instrSheet = $spreadsheet->createSheet();
            $instrSheet->setTitle('Instrucciones');
            $spreadsheet->setActiveSheetIndex(0); // volver al índice 0 después

            // Estilos para la hoja de instrucciones
            $titleStyle = [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                           'startColor' => ['rgb' => '1F4E79']],
                'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            ];
            $sectionStyle = [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                           'startColor' => ['rgb' => '2E75B6']],
            ];
            $colHeaderStyle = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                           'startColor' => ['rgb' => 'D6E4F0']],
                'borders' => ['bottom' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                           'color' => ['rgb' => '2E75B6']]],
            ];
            $infoRowStyle = [
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                           'startColor' => ['rgb' => 'FFF2CC']],
            ];
            $editRowStyle = [
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                           'startColor' => ['rgb' => 'DDEEFF']],
            ];
            $warnStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'C00000']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                           'startColor' => ['rgb' => 'FFE0E0']],
            ];

            // Título principal
            $instrSheet->mergeCells('A1:D1');
            $instrSheet->setCellValue('A1', '📋  INSTRUCCIONES DE USO — Novedades HL Express');
            $instrSheet->getStyle('A1')->applyFromArray($titleStyle);
            $instrSheet->getRowDimension(1)->setRowHeight(28);

            // Subtítulo
            $instrSheet->mergeCells('A2:D2');
            $instrSheet->setCellValue('A2', 'Complete las columnas azules (I–M) en la hoja "Novedades HL Express" y suba el archivo para procesar las resoluciones.');
            $instrSheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
            $instrSheet->getRowDimension(2)->setRowHeight(18);

            // ── Sección 1: Descripción de columnas informativas ──
            $instrSheet->mergeCells('A4:D4');
            $instrSheet->setCellValue('A4', 'COLUMNAS INFORMATIVAS (no editar — fondo naranja)');
            $instrSheet->getStyle('A4')->applyFromArray($sectionStyle);

            $instrSheet->setCellValue('A5', 'Columna');
            $instrSheet->setCellValue('B5', 'Nombre');
            $instrSheet->setCellValue('C5', 'Descripción');
            $instrSheet->setCellValue('D5', 'Ejemplo');
            $instrSheet->getStyle('A5:D5')->applyFromArray($colHeaderStyle);

            $infoColumns = [
                ['A', 'numero_orden',    'Número de orden interno del sistema.',                    'ORD-20726'],
                ['B', 'tracking_number', 'Número de tracking de HL Express (WPA.../WCO...).',       'WPA2000827970'],
                ['C', 'destinatario',    'Nombre completo del destinatario del envío.',             'Juan Pérez'],
                ['D', 'telefono',        'Teléfono de contacto del destinatario.',                  '50612345678'],
                ['E', 'direccion',       'Dirección de entrega original registrada en el sistema.', 'Calle 5, El Dorado, Panamá'],
                ['F', 'tipo_novedad_id', 'Nombre o ID del tipo de novedad registrada.',             'Novedad'],
                ['G', 'is_solved',       'Indica si la novedad ya fue resuelta (No = pendiente).',  'No'],
                ['H', 'fecha_novedad',   'Fecha y hora en que se registró la novedad en HL Express.','2026-06-17T14:30:00Z'],
            ];

            $r = 6;
            foreach ($infoColumns as [$col, $name, $desc, $ex]) {
                $instrSheet->setCellValue("A{$r}", $col);
                $instrSheet->setCellValue("B{$r}", $name);
                $instrSheet->setCellValue("C{$r}", $desc);
                $instrSheet->setCellValue("D{$r}", $ex);
                $instrSheet->getStyle("A{$r}:D{$r}")->applyFromArray($infoRowStyle);
                $r++;
            }

            // ── Sección 2: Columnas a rellenar ──
            $r++;
            $instrSheet->mergeCells("A{$r}:D{$r}");
            $instrSheet->setCellValue("A{$r}", 'COLUMNAS A COMPLETAR (fondo azul — obligatorias para procesar)');
            $instrSheet->getStyle("A{$r}:D{$r}")->applyFromArray($sectionStyle);
            $r++;

            $instrSheet->setCellValue("A{$r}", 'Columna');
            $instrSheet->setCellValue("B{$r}", 'Nombre');
            $instrSheet->setCellValue("C{$r}", 'Descripción');
            $instrSheet->setCellValue("D{$r}", 'Valores válidos / Ejemplo');
            $instrSheet->getStyle("A{$r}:D{$r}")->applyFromArray($colHeaderStyle);
            $r++;

            $editColumns = [
                ['I', 'accion',         '⚠️ OBLIGATORIO. Define si se reintenta la entrega o se devuelve al remitente.',
                                        'reintentar   ó   devolver'],
                ['J', 'nueva_solucion', 'Instrucciones u observaciones para el operador de HL Express (cómo entregar, nuevo punto, etc.).',
                                        'Llamar al cliente antes de ir. Dejar con vecino si no contesta.'],
                ['K', 'nuevo_nombre',   'Nombre del destinatario actualizado (opcional si no cambió).',
                                        'Ana González'],
                ['L', 'nuevo_telefono', 'Teléfono de contacto actualizado (opcional si no cambió).',
                                        '50698765432'],
                ['M', 'nueva_direccion','Dirección de entrega actualizada (opcional si no cambió).',
                                        'Calle 8 Nte, Casa 12, Panamá Centro'],
            ];

            foreach ($editColumns as [$col, $name, $desc, $ex]) {
                $instrSheet->setCellValue("A{$r}", $col);
                $instrSheet->setCellValue("B{$r}", $name);
                $instrSheet->setCellValue("C{$r}", $desc);
                $instrSheet->setCellValue("D{$r}", $ex);
                $instrSheet->getStyle("A{$r}:D{$r}")->applyFromArray($editRowStyle);
                $r++;
            }

            // ── Sección 3: Reglas de la columna "accion" ──
            $r++;
            $instrSheet->mergeCells("A{$r}:D{$r}");
            $instrSheet->setCellValue("A{$r}", 'VALORES VÁLIDOS PARA LA COLUMNA "accion"');
            $instrSheet->getStyle("A{$r}:D{$r}")->applyFromArray($sectionStyle);
            $r++;

            $instrSheet->setCellValue("A{$r}", 'reintentar');
            $instrSheet->setCellValue("B{$r}", 'HL Express volverá a intentar la entrega con la información actualizada (nombre, teléfono, dirección, instrucciones).');
            $instrSheet->mergeCells("B{$r}:D{$r}");
            $instrSheet->getStyle("A{$r}:D{$r}")->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']]]);
            $instrSheet->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;

            $instrSheet->setCellValue("A{$r}", 'devolver');
            $instrSheet->setCellValue("B{$r}", 'El paquete será devuelto al remitente. No se necesitan datos adicionales (nombre/teléfono/dirección).');
            $instrSheet->mergeCells("B{$r}:D{$r}");
            $instrSheet->getStyle("A{$r}:D{$r}")->applyFromArray(['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']]]);
            $instrSheet->getStyle("A{$r}")->getFont()->setBold(true);
            $r++;

            // ── Sección 4: Advertencias ──
            $r++;
            $instrSheet->mergeCells("A{$r}:D{$r}");
            $instrSheet->setCellValue("A{$r}", '⚠️  IMPORTANTE — Antes de subir el archivo');
            $instrSheet->getStyle("A{$r}:D{$r}")->applyFromArray($warnStyle);
            $r++;

            $warns = [
                '• No elimine ni reordene columnas. El sistema las procesa por posición.',
                '• No cambie los valores de las columnas A–H (información de lectura).',
                '• La columna "accion" (I) debe contener exactamente: reintentar  o  devolver (en minúsculas).',
                '• Si deja "accion" en blanco, esa fila será ignorada al procesar.',
                '• Los campos nuevo_nombre, nuevo_telefono y nueva_direccion vienen pre-rellenados; solo modifíquelos si hay cambios.',
                '• El archivo se procesa en lote: todas las filas con "accion" completada serán enviadas a HL Express.',
            ];
            foreach ($warns as $w) {
                $instrSheet->mergeCells("A{$r}:D{$r}");
                $instrSheet->setCellValue("A{$r}", $w);
                $instrSheet->getStyle("A{$r}")->getFont()->setSize(9);
                $r++;
            }

            // Auto-size instrSheet
            $instrSheet->getColumnDimension('A')->setWidth(12);
            $instrSheet->getColumnDimension('B')->setWidth(22);
            $instrSheet->getColumnDimension('C')->setWidth(70);
            $instrSheet->getColumnDimension('D')->setWidth(45);
            foreach (range(1, $r) as $rowIdx) {
                $instrSheet->getRowDimension($rowIdx)->setRowHeight(16);
            }
            $instrSheet->getRowDimension(1)->setRowHeight(28);
            $instrSheet->getStyle("C1:D{$r}")->getAlignment()
                ->setWrapText(true)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

            // Asegurar que la primera hoja quede activa al abrir
            $spreadsheet->setActiveSheetIndex(0);
            // ── Fin hoja instrucciones ────────────────────────────────────────────

            $timestamp = date('Ymd_Hi');
            $filename  = "novedades_hlexpress_{$timestamp}.xlsx";

            if (ob_get_length()) ob_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            error_log("LogisticaController::exportarNovedadesExcel error: " . $e->getMessage());
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Proxy seguro para servir el PDF de guía de HL Express.
     * GET logistica/proxyGuiaPDF?url=<ruta_relativa>
     *
     * El iframe no puede cargar PDFs de dominios externos con X-Frame-Options.
     * Este proxy descarga el PDF en el backend (con la API Key) y lo sirve
     * desde nuestro propio dominio para que el iframe funcione sin restricciones.
     */
    public function proxyGuiaPDF() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405); exit;
        }

        $rawPath = trim($_GET['path'] ?? '');
        if (empty($rawPath)) {
            http_response_code(400);
            echo 'Parámetro path requerido.';
            exit;
        }

        // Validar que la ruta sea exactamente storage/guides/<uuid>.pdf (sin traversal)
        if (!preg_match('#^storage/guides/[a-f0-9\-]+\.pdf$#i', $rawPath)) {
            http_response_code(400);
            echo 'Ruta de guía no válida.';
            exit;
        }

        require_once "modelo/forwarding.php";
        require_once __DIR__ . '/../services/providers/HLExpressProvider.php';

        try {
            $providerData = ForwardingModel::obtenerProveedorPorSlug('hlexpress');
            if (!$providerData) throw new Exception('Proveedor no configurado.');

            $credentials = json_decode($providerData['credentials'] ?? '{}', true) ?: [];
            $apiKey = $credentials['password'] ?? $credentials['apiKey'] ?? '';
            $baseUrl = rtrim($providerData['base_url'] ?? 'https://shippmentapi.hlexpresspanama.com', '/');

            $pdfUrl = $baseUrl . '/' . $rawPath;

            $ch = curl_init($pdfUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/pdf',
                    'X-API-KEY: ' . $apiKey,
                ],
            ]);

            $pdfData   = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) throw new Exception('Error de conexión: ' . $curlError);
            if ($httpCode !== 200) throw new Exception("HL Express devolvió HTTP {$httpCode}.");

            // Servir el PDF desde nuestro dominio (el iframe lo cargará sin restricciones)
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="guia.pdf"');
            header('Content-Length: ' . strlen($pdfData));
            header('Cache-Control: private, max-age=300');
            // NO enviar X-Frame-Options para permitir la carga en iframe
            header_remove('X-Frame-Options');
            echo $pdfData;
            exit;

        } catch (Exception $e) {
            error_log('proxyGuiaPDF error: ' . $e->getMessage());
            http_response_code(502);
            echo 'No se pudo obtener el PDF: ' . $e->getMessage();
            exit;
        }
    }

    /**
     * Exportar envíos filtrados de HL Express a Excel.
     * GET logistica/exportarEnviosExcel
     * Params: status_id, tracking_number, order_number, start_date, end_date
     */
    public function exportarEnviosExcel() {
        require_once "modelo/forwarding.php";
        require_once __DIR__ . '/../utils/permissions.php';
        require_once __DIR__ . '/../services/providers/HLExpressProvider.php';
        require_once __DIR__ . '/../vendor/autoload.php';

        try {
            $providerData = ForwardingModel::obtenerProveedorPorSlug('hlexpress');
            if (!$providerData) throw new Exception("Proveedor HL Express no configurado.");

            $credentials   = json_decode($providerData['credentials']    ?? '{}', true) ?: [];
            $defaultConfig = json_decode($providerData['default_config'] ?? '{}', true) ?: [];
            $config = array_merge($defaultConfig, [
                'auth_endpoint'  => $providerData['auth_endpoint']  ?? '/api/AccountApi',
                'order_endpoint' => $providerData['order_endpoint'] ?? '/shipments',
                'auth_method'    => $providerData['auth_method']    ?? 'api_key',
            ]);

            $hlExpress = new HLExpressProvider($providerData['base_url'], $credentials, $config);

            // Construir filtros desde GET
            $filters = [];
            if (!empty($_GET['status_id']))       $filters['status_id']       = (int)$_GET['status_id'];
            if (!empty($_GET['tracking_number'])) $filters['tracking_number'] = trim($_GET['tracking_number']);
            if (!empty($_GET['order_number']))    $filters['order_number']    = trim($_GET['order_number']);
            if (!empty($_GET['start_date']))      $filters['start_date']      = trim($_GET['start_date']);
            if (!empty($_GET['end_date']))        $filters['end_date']        = trim($_GET['end_date']);
            $filters['limit'] = 50;

            // Mapa de estados HL Express
            $statusNames = [
                1=>'Guía Generada', 2=>'Bodega Origen', 3=>'En Ruta', 4=>'Entregado',
                5=>'Novedad', 6=>'Cancelado', 8=>'En Camino', 9=>'Devolución Proveedor',
                10=>'Recolección', 11=>'Recogido Transportadora', 12=>'En Tránsito',
                13=>'Bodega Destino', 14=>'Siniestro', 15=>'Incautado',
                16=>'En Proceso Devolución', 17=>'Novedad (En bodega)',
                18=>'Recibido Punto Venta', 19=>'Tránsito Dev. Proveedor',
                20=>'Novedad Devolución', 21=>'Devolución en Bodega',
                22=>'Devolución en Ruta', 23=>'Guía Indemnizada', 25=>'Abandono',
            ];

            // Obtener TODAS las páginas con los filtros activos
            $allShipments = [];
            $page = 1;
            do {
                $filters['page'] = $page;
                $result = $hlExpress->getShipmentsFiltered($filters);
                $allShipments = array_merge($allShipments, $result['data'] ?? []);
                $lastPage = $result['last_page'] ?? 1;
                $page++;
            } while ($page <= $lastPage && $page <= 20); // máx 20 páginas / 1000 registros

            // ── Crear Spreadsheet ─────────────────────────────────────────────
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Envíos HL Express');

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                           'startColor' => ['rgb' => 'BDD7EE']],
                'borders' => ['bottom' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM,
                                           'color' => ['rgb' => '2E75B6']]],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            ];

            $headers = [
                'A1' => 'Tracking / Guía',
                'B1' => 'Número de Orden',
                'C1' => 'Destinatario',
                'D1' => 'Teléfono',
                'E1' => 'Dirección',
                'F1' => 'Ciudad',
                'G1' => 'Estado',
                'H1' => 'COD (¿Sí?)',
                'I1' => 'Total COD',
                'J1' => 'Precio Envío',
                'K1' => 'Link Guía PDF',
                'L1' => 'Fecha Creación',
            ];

            foreach ($headers as $cell => $label) {
                $sheet->setCellValue($cell, $label);
                $sheet->getStyle($cell)->applyFromArray($headerStyle);
            }

            $hlBaseUrl = 'https://shippmentapi.hlexpresspanama.com/';
            $row = 2;
            foreach ($allShipments as $s) {
                $dest   = $s['shipment_destination'] ?? [];
                $city   = $dest['city']['name'] ?? ($s['city']['name'] ?? '');
                $estado = $statusNames[$s['shipment_status_id'] ?? 0] ?? ('Estado #' . ($s['shipment_status_id'] ?? '?'));
                $pdfUrl = !empty($s['guide_link']) ? $hlBaseUrl . $s['guide_link'] : '';
                $fecha  = !empty($s['created_at'])
                    ? (new \DateTime($s['created_at']))->setTimezone(new \DateTimeZone('America/Panama'))->format('d/m/Y H:i')
                    : '';

                $sheet->setCellValue("A{$row}", $s['tracking_number'] ?? '');
                $sheet->setCellValue("B{$row}", $s['order_number']    ?? '');
                $sheet->setCellValue("C{$row}", $dest['full_name']    ?? '');
                $sheet->setCellValue("D{$row}", $dest['phone_number'] ?? '');
                $sheet->setCellValue("E{$row}", $dest['address']      ?? '');
                $sheet->setCellValue("F{$row}", $city);
                $sheet->setCellValue("G{$row}", $estado);
                $sheet->setCellValue("H{$row}", ($s['is_cod'] ?? false) ? 'Sí' : 'No');
                $sheet->setCellValue("I{$row}", $s['total_cod']       ?? 0);
                $sheet->setCellValue("J{$row}", $s['shipment_price']  ?? 0);
                if ($pdfUrl) {
                    $sheet->getCell("K{$row}")->getHyperlink()->setUrl($pdfUrl);
                    $sheet->setCellValue("K{$row}", 'Ver PDF');
                    $sheet->getStyle("K{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0563C1'))->setUnderline(true);
                }
                $sheet->setCellValue("L{$row}", $fecha);

                // Fila alternada
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:L{$row}")->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F2F9FF');
                }

                // Formato numérico para COD y precio
                $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

                $row++;
            }

            // Auto-size columnas
            foreach (range('A', 'L') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(45);
            $sheet->getColumnDimension('G')->setAutoSize(false)->setWidth(22);

            // Freeze encabezado y auto-filtro
            $sheet->freezePane('A2');
            $sheet->setAutoFilter("A1:L" . max(1, $row - 1));

            // Fila de totales
            if ($row > 2) {
                $totalRow = $row;
                $sheet->setCellValue("H{$totalRow}", 'TOTAL:');
                $sheet->setCellValue("I{$totalRow}", "=SUM(I2:I" . ($row - 1) . ")");
                $sheet->setCellValue("J{$totalRow}", "=SUM(J2:J" . ($row - 1) . ")");
                $sheet->getStyle("H{$totalRow}:J{$totalRow}")->getFont()->setBold(true);
                $sheet->getStyle("I{$totalRow}:J{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("H{$totalRow}:J{$totalRow}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('BDD7EE');
            }

            // ── Metadatos del filtro ──────────────────────────────────────────
            $spreadsheet->getProperties()
                ->setTitle('Envíos HL Express')
                ->setSubject('Exportación de envíos')
                ->setDescription('Generado el ' . date('Y-m-d H:i'));

            $timestamp = date('Ymd_Hi');
            $filename  = "envios_hlexpress_{$timestamp}.xlsx";

            if (ob_get_length()) ob_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header('Cache-Control: max-age=0');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (Exception $e) {
            error_log("LogisticaController::exportarEnviosExcel error: " . $e->getMessage());
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Resolver novedades de HL Express de forma masiva desde un Excel subido.
     * POST logistica/resolverNovedadesMasivo
     */
    public function resolverNovedadesMasivo() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            exit;
        }

        require_once "modelo/forwarding.php";
        require_once "modelo/logistica.php";
        require_once "modelo/pedido.php";
        require_once __DIR__ . '/../utils/permissions.php';
        require_once __DIR__ . '/../services/providers/HLExpressProvider.php';
        require_once __DIR__ . '/../vendor/autoload.php';

        if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => 'No se recibió ningún archivo.']);
            exit;
        }

        $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;

        try {
            // Cargar el Excel
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($_FILES['archivo']['tmp_name']);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($_FILES['archivo']['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $rows  = $sheet->toArray(null, true, true, true);

            // Detectar encabezados en fila 1
            $headers = array_map('strtolower', array_map('trim', $rows[1] ?? []));
            $colMap  = array_flip($headers); // nombre_columna => letra_columna

            $required = ['numero_orden', 'accion'];
            foreach ($required as $req) {
                if (!isset($colMap[$req])) {
                    header('Content-Type: application/json', true, 400);
                    echo json_encode(['success' => false, 'message' => "Columna requerida no encontrada: {$req}"]);
                    exit;
                }
            }

            // Obtener proveedor HL Express
            $providerData = ForwardingModel::obtenerProveedorPorSlug('hlexpress');
            if (!$providerData) throw new Exception("Proveedor HL Express no configurado.");

            $credentials   = json_decode($providerData['credentials']    ?? '{}', true) ?: [];
            $defaultConfig = json_decode($providerData['default_config'] ?? '{}', true) ?: [];
            $config = array_merge($defaultConfig, [
                'auth_endpoint'  => $providerData['auth_endpoint']  ?? '/api/AccountApi',
                'order_endpoint' => $providerData['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
                'auth_method'    => $providerData['auth_method']    ?? 'bearer_jwt',
            ]);

            $hlExpress = new HLExpressProvider($providerData['base_url'], $credentials, $config);

            $exitosos   = 0;
            $errores    = [];
            $resultados = []; // Por fila
            $advertencias = [];

            foreach ($rows as $rowNum => $row) {
                if ($rowNum === 1) continue; // Saltar encabezado

                $numeroOrden = trim($row[$colMap['numero_orden']] ?? '');
                $accion      = strtolower(trim($row[$colMap['accion']] ?? ''));

                if (empty($numeroOrden) || empty($accion)) continue;
                if (!in_array($accion, ['reintentar', 'devolver'])) {
                    $errores[] = "Fila {$rowNum} ({$numeroOrden}): acción inválida '{$accion}'. Use 'reintentar' o 'devolver'.";
                    $resultados[] = [
                        'fila'   => $rowNum,
                        'orden'  => $numeroOrden,
                        'accion' => $accion,
                        'estado' => 'error',
                        'msg'    => "Acción inválida '{$accion}'. Use 'reintentar' o 'devolver'.",
                    ];
                    continue;
                }

                $isReturn = ($accion === 'devolver');

                // Obtener campos de corrección
                $nuevaSolucion  = trim($row[$colMap['nueva_solucion']  ?? ''] ?? '');
                $nuevoNombre    = trim($row[$colMap['nuevo_nombre']    ?? ''] ?? '');
                $nuevoTelefono  = trim($row[$colMap['nuevo_telefono']  ?? ''] ?? '');
                $nuevaDireccion = trim($row[$colMap['nueva_direccion'] ?? ''] ?? '');

                // Validar campos para reintentar
                if (!$isReturn && (empty($nuevaSolucion) || empty($nuevoNombre) || empty($nuevoTelefono) || empty($nuevaDireccion))) {
                    $errores[] = "Fila {$rowNum} ({$numeroOrden}): para 'reintentar' se requieren nueva_solucion, nuevo_nombre, nuevo_telefono y nueva_direccion.";
                    continue;
                }

                // Buscar pedido local por número de orden
                $buscarOrden = ltrim($numeroOrden, '#');
                $pedido = (new PedidosModel())->obtenerPedidoPorNumero($buscarOrden);
                if (!$pedido) $pedido = (new PedidosModel())->obtenerPedidoPorNumero('#' . $buscarOrden);
                if (!$pedido) {
                    $errores[] = "Fila {$rowNum} ({$numeroOrden}): pedido no encontrado en la plataforma.";
                    $resultados[] = [
                        'fila'   => $rowNum,
                        'orden'  => $numeroOrden,
                        'accion' => $accion,
                        'estado' => 'error',
                        'msg'    => 'Pedido no encontrado en la plataforma.',
                    ];
                    continue;
                }

                // Obtener tracking number
                $log = ForwardingModel::obtenerLogForwardingExitoso($pedido['id'], 'hlexpress');
                if (!$log) {
                    $errores[] = "Fila {$rowNum} ({$numeroOrden}): el pedido no fue enviado por HL Express.";
                    $resultados[] = [
                        'fila'   => $rowNum,
                        'orden'  => $numeroOrden,
                        'accion' => $accion,
                        'estado' => 'warning',
                        'msg'    => 'El pedido no fue enviado por HL Express (sin log de forwarding).',
                    ];
                    continue;
                }

                $trackingNumber = $log['external_order_id'] ?? null;
                if (empty($trackingNumber)) {
                    $decoded = json_decode($log['response_payload'] ?? '{}', true);
                    $trackingNumber = $decoded['id'] ?? $decoded['tracking_number'] ?? null;
                }

                if (empty($trackingNumber)) {
                    $errores[] = "Fila {$rowNum} ({$numeroOrden}): no se pudo obtener el número de guía.";
                    $resultados[] = [
                        'fila'   => $rowNum,
                        'orden'  => $numeroOrden,
                        'accion' => $accion,
                        'estado' => 'warning',
                        'msg'    => 'No se pudo obtener el número de guía de HL Express.',
                    ];
                    continue;
                }

                // Aplicar fallback para devolución
                if ($isReturn) {
                    if (empty($nuevoNombre))    $nuevoNombre    = $pedido['destinatario'] ?? 'Destinatario';
                    if (empty($nuevoTelefono))  $nuevoTelefono  = $pedido['telefono']     ?? '00000000';
                    if (empty($nuevaDireccion)) $nuevaDireccion = $pedido['direccion']    ?? 'Dirección';
                    if (empty($nuevaSolucion))  $nuevaSolucion  = 'Retorno al remitente solicitado por operador (bulk).';
                }

                try {
                    $payloadAPI = [
                        'tracking_number'   => $trackingNumber,
                        'is_return'         => $isReturn,
                        'contact_name'      => $nuevoNombre,
                        'contact_phone'     => $nuevoTelefono,
                        'contact_address'   => $nuevaDireccion,
                        'solve_description' => $nuevaSolucion,
                    ];

                    $hlExpress->solveReturn($payloadAPI);

                    // Actualizar estado local
                    $nuevoEstado = $isReturn ? 'Devuelto' : 'Reprogramado';
                    $obs = "Resolución Masiva HL Express - {$accion}: {$nuevaSolucion}";
                    LogisticaModel::actualizarEstado($pedido['id'], $nuevoEstado, $obs, $userId);

                    $exitosos++;
                    $resultados[] = [
                        'fila'   => $rowNum,
                        'orden'  => $numeroOrden,
                        'accion' => $accion,
                        'estado' => 'ok',
                        'msg'    => "Resuelta correctamente como '{$accion}'.",
                    ];
                } catch (Exception $apiError) {
                    $errores[] = "Fila {$rowNum} ({$numeroOrden}): {$apiError->getMessage()}";
                    $resultados[] = [
                        'fila'   => $rowNum,
                        'orden'  => $numeroOrden,
                        'accion' => $accion,
                        'estado' => 'error',
                        'msg'    => $apiError->getMessage(),
                    ];
                }
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success'      => true,
                'exitosos'     => $exitosos,
                'advertencias' => count($advertencias),
                'errores'      => $errores,
                'resultados'   => $resultados,
                'message'      => "{$exitosos} novedad(es) resuelta(s) correctamente." . (count($errores) ? " " . count($errores) . " con errores." : ""),
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Exception $e) {
            error_log("LogisticaController::resolverNovedadesMasivo error: " . $e->getMessage());
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Consultar incidencias de un pedido enviado por HL Express.
     * GET logistica/consultarIncidenciasHLExpress/<id>
     */
    public function consultarIncidenciasHLExpress($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            exit;
        }

        // Capturar cualquier output espurio (warnings/notices de PHP) para que no rompan el JSON
        ob_start();

        require_once "modelo/forwarding.php";
        require_once "modelo/pedido.php";
        require_once __DIR__ . '/../utils/permissions.php';

        $id = (int)$id;
        $pedido = PedidosModel::obtenerPedidoPorId($id);
        if (!$pedido) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'message' => 'Pedido no encontrado.']);
            exit;
        }

        $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $isAdmin = isSuperAdmin();

        $hasAccess = false;
        if ($isAdmin) {
            $hasAccess = true;
        } else {
            $isProveedor = isCliente();
            $hasAccess = $isProveedor
                ? ($pedido['id_proveedor'] == $userId)
                : ($pedido['id_cliente']   == $userId);
        }

        if (!$hasAccess) {
            header('Content-Type: application/json', true, 403);
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para ver este pedido.']);
            exit;
        }

        // Obtener el log de forwarding exitoso para hlexpress
        $log = ForwardingModel::obtenerLogForwardingExitoso($id, 'hlexpress');
        if (!$log) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'message' => 'El pedido no ha sido enviado por HL Express.']);
            exit;
        }

        // El external_order_id es el UUID interno de HL Express.
        // El guide_number para solve-return es el order_number (ej. WCO2801)
        // que viene en el response_payload del forwarding log.
        $responseDecoded = json_decode($log['response_payload'] ?? '{}', true) ?: [];
        // tracking_number (ej. V4000021620) es el identificador que acepta
        // el endpoint solve-return de HL Express como guide_number.
        $guideNumber = $responseDecoded['tracking_number']
            ?? $responseDecoded['external_order_id']
            ?? $log['external_order_id']
            ?? null;

        if (empty($guideNumber)) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => 'No se pudo obtener el número de guía de HL Express.']);
            exit;
        }

        // city_code del pedido (código postal usado como city_code en HL Express)
        $cityCode = $pedido['codigo_postal'] ?? '';

        try {
            $providerData = ForwardingModel::obtenerProveedorPorSlug('hlexpress');
            if (!$providerData) {
                throw new Exception("Proveedor HL Express no configurado en la plataforma.");
            }

            require_once __DIR__ . '/../services/providers/HLExpressProvider.php';

            $credentials = json_decode($providerData['credentials'] ?? '{}', true) ?: [];
            $defaultConfig = json_decode($providerData['default_config'] ?? '{}', true) ?: [];
            $config = array_merge($defaultConfig, [
                'auth_endpoint'  => $providerData['auth_endpoint']  ?? '/api/AccountApi',
                'order_endpoint' => $providerData['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
                'auth_method'    => $providerData['auth_method']    ?? 'bearer_jwt',
            ]);

            $hlExpress = new HLExpressProvider($providerData['base_url'], $credentials, $config);
            $incidents = $hlExpress->getIncidents($guideNumber);

            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $incidents]);
            exit;
        } catch (Exception $e) {
            error_log("LogisticaController::consultarIncidenciasHLExpress error: " . $e->getMessage());
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    /**
     * Resolver novedad de un pedido enviado por HL Express.
     * POST logistica/resolverNovedad/<id>
     */
    public function resolverNovedad($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            exit;
        }

        require_once "modelo/forwarding.php";
        require_once "modelo/pedido.php";
        require_once "modelo/logistica.php";
        require_once __DIR__ . '/../utils/permissions.php';

        $id = (int)$id;
        $pedido = PedidosModel::obtenerPedidoPorId($id);
        if (!$pedido) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'message' => 'Pedido no encontrado.']);
            exit;
        }

        $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        $isAdmin = isSuperAdmin();

        $hasAccess = false;
        if ($isAdmin) {
            $hasAccess = true;
        } else {
            $isProveedor = isCliente();
            if ($isProveedor) {
                // Los usuarios con rol Proveedor pueden resolver cualquier novedad de HL Express
                // ya que el tab Novedades muestra TODOS los pedidos de HL Express sin filtrar
                // por propietario. La seguridad real la aplica la API de HL Express.
                $hasAccess = true;
            } else {
                // Clientes: solo sus propios pedidos
                $hasAccess = ($pedido['id_cliente'] == $userId);
            }
        }

        if (!$hasAccess) {
            header('Content-Type: application/json', true, 403);
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para acceder a este pedido.']);
            exit;
        }

        // Obtener el log de forwarding exitoso para hlexpress
        $log = ForwardingModel::obtenerLogForwardingExitoso($id, 'hlexpress');
        if (!$log) {
            header('Content-Type: application/json', true, 404);
            echo json_encode(['success' => false, 'message' => 'El pedido no ha sido enviado por HL Express.']);
            exit;
        }

        // El external_order_id es el UUID interno de HL Express.
        // El guide_number para solve-return es el tracking_number (ej. V4000021620)
        // que viene en el response_payload del forwarding log.
        $responseDecoded = json_decode($log['response_payload'] ?? '{}', true) ?: [];
        $guideNumber = $responseDecoded['tracking_number']
            ?? $responseDecoded['external_order_id']
            ?? $log['external_order_id']
            ?? null;

        if (empty($guideNumber)) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => 'No se pudo obtener el número de guía de HL Express.']);
            exit;
        }

        // city_code del pedido (código postal usado como city_code en HL Express)
        $cityCode = $pedido['codigo_postal'] ?? '';

        // Validar parámetros del body POST/JSON
        $rawInput = file_get_contents('php://input');
        $inputDecoded = json_decode($rawInput, true) ?: [];

        $isReturn         = isset($_POST['is_return']) ? (filter_var($_POST['is_return'], FILTER_VALIDATE_BOOLEAN)) : (isset($inputDecoded['is_return']) ? filter_var($inputDecoded['is_return'], FILTER_VALIDATE_BOOLEAN) : false);
        $contactName      = trim($_POST['contact_name'] ?? $inputDecoded['contact_name'] ?? '');
        $contactPhone     = trim($_POST['contact_phone'] ?? $inputDecoded['contact_phone'] ?? '');
        $contactAddress   = trim($_POST['contact_address'] ?? $inputDecoded['contact_address'] ?? '');
        $solveDescription = trim($_POST['solve_description'] ?? $inputDecoded['solve_description'] ?? '');

        // Validaciones
        if (!$isReturn) {
            if (empty($contactName) || empty($contactPhone) || empty($contactAddress) || empty($solveDescription)) {
                header('Content-Type: application/json', true, 400);
                echo json_encode(['success' => false, 'message' => 'Todos los campos del formulario son obligatorios para reprogramar.']);
                exit;
            }
        } else {
            // Si es devolución/retorno, usar valores de fallback si vienen vacíos
            if (empty($contactName)) $contactName = $pedido['destinatario'] ?? 'Destinatario';
            if (empty($contactPhone)) $contactPhone = $pedido['telefono'] ?? '00000000';
            if (empty($contactAddress)) $contactAddress = $pedido['direccion'] ?? 'Dirección';
            if (empty($solveDescription)) $solveDescription = 'Retorno al remitente solicitado por el operador.';
        }

        try {
            $providerData = ForwardingModel::obtenerProveedorPorSlug('hlexpress');
            if (!$providerData) {
                throw new Exception("Proveedor HL Express no configurado en la plataforma.");
            }

            require_once __DIR__ . '/../services/providers/HLExpressProvider.php';

            $credentials = json_decode($providerData['credentials'] ?? '{}', true) ?: [];
            $defaultConfig = json_decode($providerData['default_config'] ?? '{}', true) ?: [];
            $config = array_merge($defaultConfig, [
                'auth_endpoint'  => $providerData['auth_endpoint']  ?? '/api/AccountApi',
                'order_endpoint' => $providerData['order_endpoint'] ?? '/api/Orders/OrderAndOrderDetail',
                'auth_method'    => $providerData['auth_method']    ?? 'bearer_jwt',
            ]);

            $hlExpress = new HLExpressProvider($providerData['base_url'], $credentials, $config);

            // Armar el payload para la API externa con los campos exactos de HL Express
            $payloadAPI = [
                'guide_number'                      => $guideNumber,
                'is_return'                         => $isReturn,
                'contact_name'                      => $contactName,   // alias → mapeado en solveReturn()
                'contact_phone'                     => $contactPhone,
                'contact_address'                   => $contactAddress,
                'solve_description'                 => $solveDescription,
                'customer_destination_city_code'    => $cityCode,
            ];

            // Llamada real solveReturn de la API de HL Express
            $responseAPI = $hlExpress->solveReturn($payloadAPI);

            // Si es exitoso, actualizar el estado del pedido local
            $nuevoEstadoNombre = $isReturn ? 'Devuelto' : 'Reprogramado';
            $observacionesLocal = "Resolución de Novedad HL Express - Nombre: {$contactName}, Tel: {$contactPhone}, Dirección: {$contactAddress}. Instrucción: {$solveDescription}";

            $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;

            // Cambiar el estado del pedido
            $localSuccess = LogisticaModel::actualizarEstado($id, $nuevoEstadoNombre, $observacionesLocal, $userId);

            if (!$localSuccess) {
                throw new Exception("Novedad resuelta en HL Express, pero no se pudo actualizar el estado local del pedido.");
            }

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Novedad resuelta y estado del pedido actualizado correctamente.',
                'api_response' => $responseAPI
            ]);
            exit;
        } catch (Exception $e) {
            error_log("LogisticaController::resolverNovedad error: " . $e->getMessage());
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}
