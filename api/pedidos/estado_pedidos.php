<?php
/**
 * GET /api/pedidos/estado_pedidos
 *
 * Devuelve el estado actual de los pedidos.
 * A diferencia de /historial (que muestra cambios de estado),
 * este endpoint muestra directamente el estado vigente de cada pedido.
 *
 * Requiere autenticación Bearer JWT.
 *
 * Query Params (todos opcionales):
 * ------------------------------------------------------------------
 * | Parámetro    | Tipo   | Descripción                            |
 * |--------------|--------|----------------------------------------|
 * | numero_orden | string | Número de orden exacto                 |
 * | id_estado    | int    | Filtrar por estado actual              |
 * | id_cliente   | int    | Filtrar por cliente                    |
 * | id_proveedor | int    | Filtrar por proveedor/mensajero        |
 * | fecha_desde  | string | Fecha ingreso desde (Y-m-d)            |
 * | fecha_hasta  | string | Fecha ingreso hasta (Y-m-d)            |
 * | page         | int    | Página (default: 1)                    |
 * | limit        | int    | Registros por página (default: 20, máx: 100) |
 * ------------------------------------------------------------------
 *
 * Response:
 * {
 *   "success": true,
 *   "message": "Se encontraron 3 pedidos.",
 *   "data": [
 *     {
 *       "id": 45,
 *       "numero_orden": "81154737",
 *       "destinatario": "Juan Pérez",
 *       "id_estado": 1,
 *       "estado_actual": "En bodega",
 *       "observacion_estado": "Cliente solicita entrega en horario PM",
 *       "fecha_observacion_estado": "2026-03-10 10:15:00",
 *       "observacion_por": "María López",
 *       "fecha_ingreso": "2026-03-10 09:00:00",
 *       "fecha_actualizacion": "2026-03-10 09:00:00"
 *     }
 *   ],
 *   "pagination": { ... }
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

    // ── Filtros ────────────────────────────────────────────────────────────
    $filtros = [];

    if (!empty($_GET['numero_orden'])) {
        $filtros['numero_orden'] = trim($_GET['numero_orden']);
    }

    if (!empty($_GET['id_estado']) && is_numeric($_GET['id_estado'])) {
        $filtros['id_estado'] = (int)$_GET['id_estado'];
    }

    if (!empty($_GET['id_cliente']) && is_numeric($_GET['id_cliente'])) {
        $filtros['id_cliente'] = (int)$_GET['id_cliente'];
    }

    if (!empty($_GET['id_proveedor']) && is_numeric($_GET['id_proveedor'])) {
        $filtros['id_proveedor'] = (int)$_GET['id_proveedor'];
    }

    if (!empty($_GET['fecha_desde'])) {
        $d = $_GET['fecha_desde'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            responder(false, 'Formato de fecha_desde inválido. Use Y-m-d (ej: 2026-03-01).', null, 400);
        }
        $filtros['fecha_desde'] = $d;
    }

    if (!empty($_GET['fecha_hasta'])) {
        $h = $_GET['fecha_hasta'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $h)) {
            responder(false, 'Formato de fecha_hasta inválido. Use Y-m-d (ej: 2026-03-31).', null, 400);
        }
        $filtros['fecha_hasta'] = $h;
    }

    if (!empty($filtros['fecha_desde']) && !empty($filtros['fecha_hasta'])) {
        if ($filtros['fecha_desde'] > $filtros['fecha_hasta']) {
            responder(false, 'fecha_desde no puede ser posterior a fecha_hasta.', null, 400);
        }
    }

    // ── Paginación ─────────────────────────────────────────────────────────
    $page  = isset($_GET['page'])  ? max(1, (int)$_GET['page'])            : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;

    // ── Consulta ───────────────────────────────────────────────────────────
    $resultado = PedidosModel::obtenerEstadoActualPedidos($filtros, $page, $limit);

    // Formatear fechas a America/Managua (UTC-6)
    foreach ($resultado['data'] as &$item) {
        $fechasAFormatear = ['fecha_observacion_estado', 'fecha_ingreso', 'fecha_actualizacion'];
        foreach ($fechasAFormatear as $campo) {
            if (!empty($item[$campo])) {
                try {
                    $systemTz = date_default_timezone_get();
                    $dt = new DateTime($item[$campo], new DateTimeZone($systemTz));
                    $dt->setTimezone(new DateTimeZone('America/Managua'));
                    $item[$campo] = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    // Ignorar errores de fecha
                }
            }
        }
    }
    unset($item);

    $total = $resultado['pagination']['total'];
    $msg   = $total === 0
        ? 'No se encontraron pedidos con los filtros indicados.'
        : "Se encontraron $total pedido" . ($total === 1 ? '' : 's') . ".";

    responder(true, $msg, $resultado['data'], 200, ['pagination' => $resultado['pagination']]);

} catch (Throwable $e) {
    error_log('[api/pedidos/estado_pedidos] Error: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    responder(false, 'Error interno del servidor.', null, 500);
}
