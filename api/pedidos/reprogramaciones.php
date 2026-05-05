<?php
/**
 * GET /api/pedidos/reprogramaciones
 *
 * Endpoint estándar para que los clientes (operadores) consulten
 * sus órdenes en estado Reprogramado (ID 4) y la nueva fecha de entrega.
 *
 * El token JWT limita automáticamente los resultados al cliente autenticado,
 * a menos que sea Administrador (ve todos los pedidos).
 *
 * Requiere autenticación Bearer JWT.
 *
 * ─── Query Params (todos opcionales) ───────────────────────────────────────
 *  numero_orden   string   Filtrar por número de orden exacto
 *  fecha_desde    string   Reprogramaciones desde esta fecha (Y-m-d)
 *  fecha_hasta    string   Reprogramaciones hasta esta fecha (Y-m-d)
 *  page           int      Página (default: 1)
 *  limit          int      Registros por página (default: 20, máx: 100)
 * ───────────────────────────────────────────────────────────────────────────
 *
 * ─── Response ───────────────────────────────────────────────────────────────
 * {
 *   "success": true,
 *   "message": "Se encontraron 5 órdenes reprogramadas.",
 *   "data": [
 *     {
 *       "numero_orden":       "81154737",
 *       "destinatario":       "Juan Pérez",
 *       "direccion":          "Av. Central 123",
 *       "estado_actual":      "Reprogramado",
 *       "fecha_entrega":      "2026-05-20",
 *       "motivo":             "Cliente ausente en primer intento",
 *       "reprogramado_por":   "rutaexmex.api",
 *       "fecha_reprogramacion": "2026-05-05 14:30:00"
 *     }
 *   ],
 *   "pagination": {
 *     "total": 5,
 *     "per_page": 20,
 *     "current_page": 1,
 *     "total_pages": 1,
 *     "has_next": false,
 *     "has_prev": false
 *   }
 * }
 * ───────────────────────────────────────────────────────────────────────────
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

    // ── 1. Autenticación ───────────────────────────────────────────────────
    $token = AuthMiddleware::obtenerTokenDeHeaders();
    if (!$token) {
        responder(false, 'Token de autorización requerido.', null, 401);
    }

    $auth       = new AuthMiddleware();
    $validacion = $auth->validarToken($token);
    if (!$validacion['success']) {
        responder(false, $validacion['message'] ?? 'Token inválido o expirado.', null, 401);
    }

    $authUserId   = (int)($validacion['data']['id']  ?? 0);
    $authUserRole = (int)($validacion['data']['rol'] ?? 0);
    $isAdmin      = ($authUserRole === (defined('ROL_ADMIN') ? ROL_ADMIN : 1));

    // ── 2. Parámetros de filtro ────────────────────────────────────────────
    $filtros = [];

    // Los no-admin solo ven sus propios pedidos
    if (!$isAdmin) {
        $filtros['id_usuario_pertenencia'] = $authUserId;
    }

    if (!empty($_GET['numero_orden'])) {
        $filtros['numero_orden'] = trim($_GET['numero_orden']);
    }

    // Validación de fechas
    if (!empty($_GET['fecha_desde'])) {
        $d = trim($_GET['fecha_desde']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            responder(false, "Formato de fecha_desde inválido. Use YYYY-MM-DD.", null, 400);
        }
        $filtros['fecha_desde'] = $d;
    }

    if (!empty($_GET['fecha_hasta'])) {
        $h = trim($_GET['fecha_hasta']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $h)) {
            responder(false, "Formato de fecha_hasta inválido. Use YYYY-MM-DD.", null, 400);
        }
        $filtros['fecha_hasta'] = $h;
    }

    if (!empty($filtros['fecha_desde']) && !empty($filtros['fecha_hasta'])) {
        if ($filtros['fecha_desde'] > $filtros['fecha_hasta']) {
            responder(false, 'fecha_desde no puede ser posterior a fecha_hasta.', null, 400);
        }
    }

    // ── 3. Paginación ──────────────────────────────────────────────────────
    $page  = isset($_GET['page'])  ? max(1, (int)$_GET['page'])            : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;

    // ── 4. Consulta ────────────────────────────────────────────────────────
    $resultado = PedidosModel::obtenerReprogramaciones($filtros, $page, $limit);

    // ── 5. Normalizar zona horaria a America/Managua ───────────────────────
    $tz = new DateTimeZone('America/Managua');
    foreach ($resultado['data'] as &$item) {
        foreach (['fecha_reprogramacion'] as $campo) {
            if (!empty($item[$campo])) {
                try {
                    $dt = new DateTime($item[$campo], new DateTimeZone(date_default_timezone_get()));
                    $dt->setTimezone($tz);
                    $item[$campo] = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) { /* dejar como está */ }
            }
        }
    }
    unset($item);

    // ── 6. Respuesta ───────────────────────────────────────────────────────
    $total = $resultado['pagination']['total'];
    $msg   = $total === 0
        ? 'No se encontraron órdenes reprogramadas con los filtros indicados.'
        : "Se encontraron {$total} orden" . ($total === 1 ? '' : 'es') . " reprogramada" . ($total === 1 ? '' : 's') . ".";

    responder(true, $msg, $resultado['data'], 200, ['pagination' => $resultado['pagination']]);

} catch (Throwable $e) {
    error_log('[api/pedidos/reprogramaciones] Error: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    responder(false, 'Error interno del servidor.', null, 500);
}
