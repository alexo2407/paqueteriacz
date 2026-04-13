<?php

require_once __DIR__ . '/../modelo/producto.php';

/**
 * ProductosController
 *
 * Controlador para operaciones CRUD de productos y consultas relacionadas
 * con inventario. Valida entradas mínimas y delega persistencia a
 * `ProductoModel`.
 */
class ProductosController
{
    /**
     * Listar productos con inventario
     * @return array
     */
    public function listar()
    {
        return ProductoModel::listarConInventario();
    }

    /**
     * Obtener detalle de producto
     * @param int $id
     * @return array|null
     */
    public function ver($id)
    {
        $p = ProductoModel::obtenerPorId($id);
        if (!$p) return null;
        $p['stock_total'] = ProductoModel::obtenerStockTotal($id);
        return $p;
    }

    /**
     * Crear producto desde formulario/data
     * @param array $data ['nombre','descripcion','precio_usd']
     * @return array ['success'=>bool,'message'=>string,'id'=>int|null]
     */
    public function crear(array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') {
            return ['success' => false, 'message' => 'El nombre es obligatorio.', 'id' => null];
        }

        $sku         = $data['sku']         ?? null;
        $descripcion = $data['descripcion'] ?? null;
        $precio      = $data['precio_usd']  ?? null;

        // Obtener el usuario actual de la sesión para asignar como creador
        $idUsuarioCreador = (int)($_SESSION['idUsuario'] ?? 0) ?: null;

        $id = ProductoModel::crear($nombre, $sku, $descripcion, $precio, $idUsuarioCreador);
        if ($id === null) {
            return ['success' => false, 'message' => 'No fue posible crear el producto.', 'id' => null];
        }
        return ['success' => true, 'message' => 'Producto creado correctamente.', 'id' => $id];
    }

    /**
     * Actualizar producto
     * @param int $id
     * @param array $data
     * @return array
     */
    public function actualizar($id, array $data)
    {
        $nombre = trim($data['nombre'] ?? '');
        if ($nombre === '') {
            return ['success' => false, 'message' => 'El nombre es obligatorio.'];
        }

        // Pasar el array completo al modelo para que maneje todos los campos
        $ok = ProductoModel::actualizar($id, $data);
        if (!$ok) {
            return ['success' => false, 'message' => 'No fue posible actualizar el producto.'];
        }
        return ['success' => true, 'message' => 'Producto actualizado correctamente.'];
    }

    /**
     * Eliminar producto
     * @param int $id
     * @return array
     */
    public function eliminar($id)
    {
        if ($id <= 0) return ['success' => false, 'message' => 'ID inválido.'];
        $ok = ProductoModel::eliminar($id);
        if (!$ok) return ['success' => false, 'message' => 'No fue posible eliminar el producto.'];
        return ['success' => true, 'message' => 'Producto eliminado correctamente.'];
    }

    /**
     * GET /productos/exportar — descarga XLSX de productos filtrados
     */
    public function exportar() {
        if (empty($_SESSION['idUsuario'])) {
            http_response_code(403);
            echo 'Debes iniciar sesión.';
            exit;
        }

        require_once __DIR__ . '/../utils/permissions.php';
        $filtroUsuarioCreador = getIdUsuarioCreadorFilter();
        
        $filtros = [];
        if (!empty($_GET['categoria'])) $filtros['categoria_id'] = $_GET['categoria'];
        if (!empty($_GET['marca'])) $filtros['marca'] = $_GET['marca'];
        if (isset($_GET['estado']) && $_GET['estado'] !== '') $filtros['activo'] = $_GET['estado'] === '1';
        
        if (isSuperAdmin() && !empty($_GET['proveedor'])) {
            $filtroUsuarioCreador = (int)$_GET['proveedor'];
        }

        if (empty($filtros)) {
            $productos = ProductoModel::listarConInventario($filtroUsuarioCreador, false);
        } else {
            $productos = ProductoModel::listarConFiltros($filtros);
            if ($filtroUsuarioCreador !== null) {
                $productos = array_filter($productos, function($p) use ($filtroUsuarioCreador) {
                    $cId = isset($p['id_usuario_creador']) ? (int)$p['id_usuario_creador'] : null;
                    if ($cId === null) return true;
                    if (is_array($filtroUsuarioCreador)) {
                        return in_array($cId, $filtroUsuarioCreador, true);
                    }
                    return $cId === $filtroUsuarioCreador;
                });
                $productos = array_values($productos);
            }
        }

        $nombreArchivo = 'productos_export_' . date('Ymd_His') . '.xlsx';
        require_once __DIR__ . '/../vendor/autoload.php';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Productos');

        $headers = ['ID', 'Nombre', 'Stock', 'Cliente / Creador'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '107C41']], // Verde Excel
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
        $sheet->freezePane('A2');

        $row = 2;
        foreach ($productos as $p) {
            $sheet->setCellValue('A' . $row, $p['id']);
            $sheet->setCellValue('B' . $row, $p['nombre']);
            $sheet->setCellValue('C' . $row, (int)($p['stock_total'] ?? 0));
            $sheet->setCellValue('D' . $row, $p['creador_nombre'] ?: 'N/A');
            $row++;
        }

        if ($row > 2) {
            $sheet->getStyle('A1:D' . ($row - 1))->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}
