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

        $trackingNumber = $log['external_order_id'] ?? null;
        if (empty($trackingNumber)) {
            $responseDecoded = json_decode($log['response_payload'] ?? '{}', true);
            $trackingNumber = $responseDecoded['id'] ?? $responseDecoded['external_order_id'] ?? $responseDecoded['tracking_number'] ?? null;
        }

        if (empty($trackingNumber)) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => 'No se pudo obtener el número de guía de HL Express.']);
            exit;
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
            $incidents = $hlExpress->getIncidents($trackingNumber);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $incidents]);
            exit;
        } catch (Exception $e) {
            error_log("LogisticaController::consultarIncidenciasHLExpress error: " . $e->getMessage());
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
            $hasAccess = $isProveedor
                ? ($pedido['id_proveedor'] == $userId)
                : ($pedido['id_cliente']   == $userId);
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

        $trackingNumber = $log['external_order_id'] ?? null;
        if (empty($trackingNumber)) {
            $responseDecoded = json_decode($log['response_payload'] ?? '{}', true);
            $trackingNumber = $responseDecoded['id'] ?? $responseDecoded['external_order_id'] ?? $responseDecoded['tracking_number'] ?? null;
        }

        if (empty($trackingNumber)) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['success' => false, 'message' => 'No se pudo obtener el número de guía de HL Express.']);
            exit;
        }

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

            // Armar el payload para la API externa
            $payloadAPI = [
                'tracking_number'   => $trackingNumber,
                'is_return'         => $isReturn,
                'contact_name'      => $contactName,
                'contact_phone'     => $contactPhone,
                'contact_address'   => $contactAddress,
                'solve_description' => $solveDescription
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
