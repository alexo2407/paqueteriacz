<?php
/**
 * GET /api/productos/listar
 *
 * Devuelve la lista de productos con inventario agregado (stock_total).
 * Respuesta: { success, message, data }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../modelo/producto.php';
require_once __DIR__ . '/../utils/responder.php';
require_once __DIR__ . '/../utils/autenticacion.php';
require_once __DIR__ . '/../../utils/permissions.php';

// Verificar autenticación
$token = AuthMiddleware::obtenerTokenDeHeaders();
if (!$token) {
    responder(false, 'Token de autorización requerido.', null, 401);
    exit;
}

$auth = new AuthMiddleware();
$check = $auth->validarToken($token);
if (!$check['success']) {
    responder(false, 'Token inválido o expirado.', null, 401);
    exit;
}

try {
    // Pagination params
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50; // Higher default for products
    $offset = ($page - 1) * $limit;
    
    $filtros = [
        'limit' => $limit,
        'offset' => $offset
    ];

    // Support optional filters from GET
    if (isset($_GET['categoria_id'])) $filtros['categoria_id'] = (int)$_GET['categoria_id'];
    if (isset($_GET['marca'])) $filtros['marca'] = $_GET['marca'];
    if (isset($_GET['activo'])) $filtros['activo'] = ($_GET['activo'] === '1' || $_GET['activo'] === 'true');
    
    // Obtener filtro por usuario creador según rol del usuario logueado
    $filtroUsuario = getIdUsuarioCreadorFilter();
    if ($filtroUsuario !== null) {
        $filtros['id_usuario_creador'] = $filtroUsuario;
    } else {
        // Admin, Vendedor o Repartidor pueden ver todos o filtrar por GET si se especifica
        if (isset($_GET['id_cliente'])) {
            $filtros['id_usuario_creador'] = (int)$_GET['id_cliente'];
        } elseif (isset($_GET['id_usuario'])) {
            $filtros['id_usuario_creador'] = (int)$_GET['id_usuario'];
        }
    }
    
    // Switch to listarConFiltros which supports pagination
    $productos = ProductoModel::listarConFiltros($filtros);
    $total = ProductoModel::contarConFiltros($filtros);
    $totalPages = ceil($total / $limit);

    // Si se solicita detalle de stock, anexar movimientos de stock por producto
    $includeStock = isset($_GET['include_stock']) && ($_GET['include_stock'] === '1' || strtolower($_GET['include_stock']) === 'true');
    if ($includeStock && is_array($productos)) {
        foreach ($productos as &$p) {
            $pid = isset($p['id']) ? (int)$p['id'] : null;
            if ($pid !== null) {
                $p['stock_entries'] = ProductoModel::listarStockPorProducto($pid);
            } else {
                $p['stock_entries'] = [];
            }
        }
        unset($p);
    }

    $pagination = [
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int)$totalPages
        ]
    ];

    responder(true, 'Listado de productos', $productos, 200, $pagination);
} catch (Exception $e) {
    responder(false, 'Error al listar productos: ' . $e->getMessage(), null, 500);
}

?>
