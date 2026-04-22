<?php
/**
 * GET /api/pedidos/historial
 *
 * Devuelve el historial de cambios de estado de los pedidos.
 * Para cada registro muestra: pedido, estado anterior y su nombre,
 * estado nuevo y su nombre, comentario/observación del cambio,
 * quién lo realizó y la fecha del cambio.
 *
 * Requiere autenticación Bearer JWT.
 *
 * Query Params (todos opcionales):
 * ------------------------------------------------------------------
 * | Parámetro          | Tipo    | Descripción                      |
 * |--------------------|---------|----------------------------------|
 * | numero_orden       | string  | Número de orden del pedido       |
 * | id_pedido          | int     | ID interno del pedido            |
 * | id_estado_anterior | int     | Filtrar por estado anterior      |
 * | id_estado_nuevo    | int     | Filtrar por estado nuevo         |
 * | estados            | string  | IDs separados por coma           |
 * |                    |         | (coincide anterior O nuevo)      |
 * | fecha_desde        | string  | Fecha inicio cambio (Y-m-d)      |
 * | fecha_hasta        | string  | Fecha fin cambio (Y-m-d)         |
 * | id_usuario         | int     | Usuario que realizó el cambio    |
 * | page               | int     | Página (default: 1)              |
 * | limit              | int     | Registros por página (default: 20, máx: 100) |
 * ------------------------------------------------------------------
 *
 * Response:
 * {
 *   "success": true,
 *   "message": "...",
 *   "data": [
 *     {
 *       "id": 12,
 *       "id_pedido": 45,
 *       "numero_orden": "100045",
 *       "id_estado_anterior": 1,
 *       "estado_anterior": "Pendiente",
 *       "id_estado_nuevo": 3,
 *       "estado_nuevo": "En tránsito",
 *       "comentario": "Recogido por mensajero",
 *       "id_usuario": 7,
 *       "realizado_por": "Juan Pérez",
 *       "fecha_cambio": "2026-03-04 09:30:00"
 *     }
 *   ],
 *   "pagination": {
 *     "total": 150,
 *     "per_page": 20,
 *     "current_page": 1,
 *     "total_pages": 8,
 *     "has_next": true,
 *     "has_prev": false
 *   }
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo GET permitido
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Use GET.']);
    exit;
}

try {
    require_once __DIR__ . '/../utils/autenticacion.php';
    require_once __DIR__ . '/../utils/responder.php';
    require_once __DIR__ . '/../../modelo/pedido.php';

    // ── Autenticación ──────────────────────────────────────────────────────
    $token = AuthMiddleware::obtenerTokenDeHeaders();
    if (!$token) {
        responder(false, 'Token de autorización requerido.', null, 401);
    }

    $auth      = new AuthMiddleware();
    $validacion = $auth->validarToken($token);
    if (!$validacion['success']) {
        responder(false, $validacion['message'], null, 401);
    }

    // ── Parámetros de filtro ────────────────────────────────────────────────
    $filtros = [];

    if (!empty($_GET['numero_orden'])) {
        $filtros['numero_orden'] = trim($_GET['numero_orden']);
    }

    if (!empty($_GET['id_pedido']) && is_numeric($_GET['id_pedido'])) {
        $filtros['id_pedido'] = (int)$_GET['id_pedido'];
    }

    if (!empty($_GET['id_estado_anterior']) && is_numeric($_GET['id_estado_anterior'])) {
        $filtros['id_estado_anterior'] = (int)$_GET['id_estado_anterior'];
    }

    if (!empty($_GET['id_estado_nuevo']) && is_numeric($_GET['id_estado_nuevo'])) {
        $filtros['id_estado_nuevo'] = (int)$_GET['id_estado_nuevo'];
    }

    // Filtro múltiple: ?estados=1,2,5  → coincide con anterior O nuevo
    if (!empty($_GET['estados'])) {
        $rawIds = explode(',', $_GET['estados']);
        $ids    = array_filter(array_map('intval', $rawIds));
        if (!empty($ids)) {
            $filtros['id_estados'] = array_values($ids);
        }
    }

    // Rango de fechas (validación básica de formato)
    if (!empty($_GET['fecha_desde'])) {
        $d = $_GET['fecha_desde'];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $filtros['fecha_desde'] = $d;
        } else {
            responder(false, 'Formato de fecha_desde inválido. Use Y-m-d (ej: 2026-03-01).', null, 400);
        }
    }

    if (!empty($_GET['fecha_hasta'])) {
        $h = $_GET['fecha_hasta'];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $h)) {
            $filtros['fecha_hasta'] = $h;
        } else {
            responder(false, 'Formato de fecha_hasta inválido. Use Y-m-d (ej: 2026-03-31).', null, 400);
        }
    }

    // Coherencia de fechas
    if (!empty($filtros['fecha_desde']) && !empty($filtros['fecha_hasta'])) {
        if ($filtros['fecha_desde'] > $filtros['fecha_hasta']) {
            responder(false, 'fecha_desde no puede ser posterior a fecha_hasta.', null, 400);
        }
    }

    if (!empty($_GET['id_usuario']) && is_numeric($_GET['id_usuario'])) {
        $filtros['id_usuario'] = (int)$_GET['id_usuario'];
    }

    if (!empty($_GET['id_cliente']) && is_numeric($_GET['id_cliente'])) {
        $filtros['id_cliente'] = (int)$_GET['id_cliente'];
    }

    if (!empty($_GET['id_proveedor']) && is_numeric($_GET['id_proveedor'])) {
        $filtros['id_proveedor'] = (int)$_GET['id_proveedor'];
    }

    // ── Paginación ─────────────────────────────────────────────────────────
    $page  = isset($_GET['page'])  ? max(1, (int)$_GET['page'])             : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit']))  : 20;

    // ── Consulta ───────────────────────────────────────────────────────────
    $resultado = PedidosModel::obtenerHistorialEstadosFiltrado($filtros, $page, $limit);

    // Ajustar zona horaria de UTC a America/Managua conservando el formato original Y-m-d H:i:s
    foreach ($resultado['data'] as &$item) {
        if (!empty($item['fecha_cambio'])) {
            try {
                $systemTz = date_default_timezone_get();
                $dt = new DateTime($item['fecha_cambio'], new DateTimeZone($systemTz));
                $dt->setTimezone(new DateTimeZone('America/Managua'));
                $item['fecha_cambio'] = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Si la fecha es inválida, se deja como está
            }
        }
    }
    unset($item);

    $total = $resultado['pagination']['total'];
    $msg   = $total === 0
        ? 'No se encontraron registros con los filtros indicados.'
        : "Se encontraron $total registros en el historial.";

    responder(true, $msg, $resultado['data'], 200, ['pagination' => $resultado['pagination']]);

} catch (Throwable $e) {
    error_log('[api/pedidos/historial] Error: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    responder(false, 'Error interno del servidor.', null, 500);
}
