<?php
require_once __DIR__ . '/../modelo/pedido.php';
require_once __DIR__ . '/../modelo/producto.php';
require_once __DIR__ . '/../modelo/usuario.php';
require_once __DIR__ . '/../modelo/moneda.php';

/**
 * ComboController
 * 
 * Controlador para gestionar combos de productos.
 * Los combos permiten que proveedores agrupen múltiples productos
 * y definan un precio único en su moneda local.
 */
class ComboController
{
    /**
     * Mostrar formulario para crear un nuevo combo
     */
    public function crear()
    {
        session_start();
        
        // Verificar que el usuario esté autenticado
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ' . RUTA_URL . 'login');
            exit;
        }

        // Obtener datos necesarios para el formulario
        $pedidosModel = new PedidosModel();
        $productoModel = new ProductoModel();
        
        $data = [
            'titulo' => 'Crear Combo de Productos',
            'proveedores' => $pedidosModel->obtenerProveedores(),
            'productos' => $productoModel->listarConInventario(),
            'monedas' => $pedidosModel->obtenerMonedas(),
            'estados' => $pedidosModel->obtenerEstados()
        ];

        require_once __DIR__ . '/../vista/modulos/combos/crear.php';
    }

    /**
     * Procesar y guardar un nuevo combo
     */
    public function guardar()
    {
        session_start();
        
        if (!isset($_SESSION['usuario_id'])) {
            echo json_encode(['success' => false, 'message' => 'No autenticado'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            // Validar datos recibidos
            if (empty($_POST['id_proveedor']) || empty($_POST['precio_local']) || empty($_POST['id_moneda'])) {
                throw new Exception("Faltan datos requeridos");
            }

            if (empty($_POST['productos']) || !is_array($_POST['productos'])) {
                throw new Exception("Debe seleccionar al menos un producto");
            }

            // Preparar datos del pedido
            $pedido = [
                'id_proveedor' => (int)$_POST['id_proveedor'],
                'precio_local' => floatval($_POST['precio_local']),
                'id_moneda' => (int)$_POST['id_moneda'],
                'destinatario' => $_POST['destinatario'] ?? 'Combo',
                'telefono' => $_POST['telefono'] ?? '',
                'direccion' => $_POST['direccion'] ?? '',
                'observaciones_combo' => $_POST['observaciones'] ?? '',
                'id_estado' => $_POST['id_estado'] ?? 1,
                'numero_orden' => $this->generarNumeroOrden()
            ];

            // Preparar items del combo
            $items = [];
            foreach ($_POST['productos'] as $producto) {
                if (!empty($producto['id']) && !empty($producto['cantidad'])) {
                    $items[] = [
                        'id_producto' => (int)$producto['id'],
                        'cantidad' => (int)$producto['cantidad']
                    ];
                }
            }

            // Crear el combo
            $pedidosModel = new PedidosModel();
            $idPedido = $pedidosModel->crearCombo($pedido, $items);

            echo json_encode([
                'success' => true,
                'message' => 'Combo creado exitosamente',
                'id_pedido' => $idPedido
            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Listar todos los combos
     */
    public function listar()
    {
        session_start();
        
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ' . RUTA_URL . 'login');
            exit;
        }

        $pedidosModel = new PedidosModel();
        
        // Aplicar filtros si existen
        $filtros = [];
        if (!empty($_GET['proveedor'])) {
            $filtros['id_proveedor'] = (int)$_GET['proveedor'];
        }
        if (!empty($_GET['fecha_inicio'])) {
            $filtros['fecha_inicio'] = $_GET['fecha_inicio'];
        }
        if (!empty($_GET['fecha_fin'])) {
            $filtros['fecha_fin'] = $_GET['fecha_fin'];
        }

        $combos = $pedidosModel->listarCombos($filtros);

        $data = [
            'titulo' => 'Listado de Combos',
            'combos' => $combos,
            'proveedores' => $pedidosModel->obtenerProveedores()
        ];

        require_once __DIR__ . '/../vista/modulos/combos/listar.php';
    }

    /**
     * Ver detalle de un combo específico
     */
    public function ver($id)
    {
        session_start();
        
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ' . RUTA_URL . 'login');
            exit;
        }

        $pedidosModel = new PedidosModel();
        $combo = $pedidosModel->obtenerPedidoPorId($id);

        if (!$combo || $combo['es_combo'] != 1) {
            header('Location: ' . RUTA_URL . 'combos/listar');
            exit;
        }

        $data = [
            'titulo' => 'Detalle del Combo #' . $combo['numero_orden'],
            'combo' => $combo
        ];

        require_once __DIR__ . '/../vista/modulos/combos/ver.php';
    }

    /**
     * Generar número de orden único
     */
    private function generarNumeroOrden()
    {
        $pedidosModel = new PedidosModel();
        $existentes = $pedidosModel->obtenerNumerosOrdenExistentes();
        
        do {
            $numero = rand(100000, 999999);
        } while (in_array($numero, $existentes));
        
        return $numero;
    }

    /**
     * API: Obtener moneda de un proveedor via AJAX
     */
    public function obtenerMonedaProveedor()
    {
        session_start();
        
        if (!isset($_SESSION['usuario_id']) || empty($_GET['id_proveedor'])) {
            echo json_encode(['success' => false], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            exit;
        }

        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT id_moneda_local FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => (int)$_GET['id_proveedor']]);
            $result = $stmt->fetch();

            if ($result && $result['id_moneda_local']) {
                $monedaModel = new MonedaModel();
                $moneda = $monedaModel->obtenerPorId($result['id_moneda_local']);
                
                echo json_encode([
                    'success' => true,
                    'moneda' => $moneda
                ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Proveedor sin moneda configurada'
                ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
        }
    }
}
