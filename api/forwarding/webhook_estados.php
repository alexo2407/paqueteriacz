<?php
/**
 * POST /api/forwarding/webhook_estados.php
 *
 * Webhook para recibir actualizaciones de estado de órdenes desde LogisPro.
 * LogisPro hace POST con el JSON de actualización; nosotros validamos, mapeamos
 * el estado y actualizamos la tabla `pedidos`.
 *
 * Autenticación: Bearer token en header Authorization.
 * El token debe coincidir con el `webhook_secret` configurado en el proveedor
 * LogisPro (forwarding_providers.credentials->webhook_secret).
 *
 * JSON esperado:
 * {
 *   "customersId": 54,
 *   "auditUser": "rutaexmex.api",
 *   "state": "Reprogramado",              // o "Orden Cerrada"
 *   "substate": "Nuevo Intento",          // o "Solicitud del cliente"
 *   "dateToReceive": "2026-05-10",        // obligatorio si state = Reprogramado
 *   "notes": "...",
 *   "ordersNumbers": [
 *     { "orderNumber": "123456" }
 *   ]
 * }
 *
 * Response:
 * {
 *   "success": true,
 *   "processed": 3,
 *   "failed": 1,
 *   "results": [
 *     { "orderNumber": "123456", "updated": true },
 *     { "orderNumber": "999999", "updated": false, "error": "Orden no encontrada" }
 *   ]
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Use POST.']);
    exit;
}

// ─── Mapeo de estados LogisPro → estados_pedidos.id ───────────────────────────
const LOGISPRO_STATE_MAP = [
    'Reprogramado' => [
        'Nuevo Intento' => 4,  // estados_pedidos.id = 4 → "Reprogramado"
    ],
    'Orden Cerrada' => [
        'Solicitud del cliente' => 17, // estados_pedidos.id = 17 → "Cancelado"
    ],
];

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../modelo/conexion.php';
    require_once __DIR__ . '/../../modelo/forwarding.php';

    // ─── 1. Autenticación Bearer ───────────────────────────────────────────
    $authHeader = $_SERVER['HTTP_AUTHORIZATION']
               ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
               ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');

    if (!$authHeader || !preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token de autorización requerido.']);
        exit;
    }
    $bearerToken = trim($m[1]);

    // Buscar proveedor LogisPro activo y validar webhook_secret
    $proveedor = ForwardingModel::obtenerProveedorPorSlug('logispro');
    if (!$proveedor || !$proveedor['activo']) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Integración no disponible.']);
        exit;
    }

    $credentials = is_string($proveedor['credentials'])
        ? json_decode($proveedor['credentials'], true)
        : $proveedor['credentials'];

    $webhookSecret = $credentials['webhook_secret'] ?? null;
    if (!$webhookSecret || !hash_equals($webhookSecret, $bearerToken)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token inválido.']);
        exit;
    }

    // ─── 2. Parsear body ───────────────────────────────────────────────────
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);

    if (!$body || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'JSON inválido.']);
        exit;
    }

    // ─── 3. Validar campos obligatorios ────────────────────────────────────
    $required = ['customersId', 'auditUser', 'state', 'substate', 'ordersNumbers'];
    foreach ($required as $field) {
        if (!isset($body[$field]) || $body[$field] === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Campo obligatorio faltante: '$field'."]);
            exit;
        }
    }

    if (!is_array($body['ordersNumbers']) || empty($body['ordersNumbers'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "'ordersNumbers' debe ser un arreglo no vacío."]);
        exit;
    }

    $state    = $body['state'];
    $substate = $body['substate'];
    $notes    = trim($body['notes'] ?? '');
    $auditUser = trim($body['auditUser'] ?? 'logispro');

    // ─── 4. Mapear estado ──────────────────────────────────────────────────
    if (!isset(LOGISPRO_STATE_MAP[$state][$substate])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Combinación de state/substate no reconocida: '$state' / '$substate'.",
            'valid_states' => array_keys(LOGISPRO_STATE_MAP),
        ]);
        exit;
    }
    $idEstadoNuevo = LOGISPRO_STATE_MAP[$state][$substate];

    // Fecha de entrega (solo obligatoria en Reprogramado)
    $fechaEntrega = null;
    if ($state === 'Reprogramado') {
        if (empty($body['dateToReceive'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "'dateToReceive' es obligatorio cuando state es 'Reprogramado'."]);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $body['dateToReceive'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "'dateToReceive' debe estar en formato YYYY-MM-DD."]);
            exit;
        }
        $fechaEntrega = $body['dateToReceive'];
    }

    // ─── 5. Procesar cada orden ────────────────────────────────────────────
    $db = (new Conexion())->conectar();
    $results   = [];
    $processed = 0;
    $failed    = 0;

    foreach ($body['ordersNumbers'] as $item) {
        $orderNumber = trim($item['orderNumber'] ?? '');
        if ($orderNumber === '') {
            $results[] = ['orderNumber' => '', 'updated' => false, 'error' => 'orderNumber vacío.'];
            $failed++;
            continue;
        }

        // Buscar pedido por numero_orden
        $stmt = $db->prepare("SELECT id, id_estado FROM pedidos WHERE numero_orden = :numero_orden LIMIT 1");
        $stmt->execute([':numero_orden' => $orderNumber]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            $results[] = ['orderNumber' => $orderNumber, 'updated' => false, 'error' => 'Orden no encontrada en el sistema.'];
            $failed++;
            continue;
        }

        $idPedido       = (int)$pedido['id'];
        $estadoAnterior = (int)$pedido['id_estado'];

        // Construir UPDATE — solo campos que existen en pedidos
        $sets   = ['id_estado = :estado', 'updated_at = NOW()'];
        $params = [':estado' => $idEstadoNuevo, ':id' => $idPedido];

        if ($fechaEntrega) {
            $sets[]                   = 'fecha_entrega = :fecha_entrega';
            $params[':fecha_entrega'] = $fechaEntrega;
        }

        $sql = "UPDATE pedidos SET " . implode(', ', $sets) . " WHERE id = :id";
        $upd = $db->prepare($sql);
        $upd->execute($params);

        // Registrar en historial de estados
        $hist = $db->prepare("
            INSERT INTO pedidos_historial_estados
                (id_pedido, id_estado_anterior, id_estado_nuevo, id_usuario, observaciones, created_at)
            VALUES
                (:id_pedido, :estado_anterior, :estado_nuevo, 0, :observaciones, NOW())
        ");
        $hist->execute([
            ':id_pedido'       => $idPedido,
            ':estado_anterior' => $estadoAnterior,
            ':estado_nuevo'    => $idEstadoNuevo,
            ':observaciones'   => "LogisPro [{$auditUser}] → {$state} / {$substate}" . ($notes ? ": {$notes}" : ''),
        ]);

        $results[] = ['orderNumber' => $orderNumber, 'updated' => true];
        $processed++;

        error_log("Webhook LogisPro: orden {$orderNumber} → estado {$idEstadoNuevo} ({$state}/{$substate})");
    }

    // ─── 6. Respuesta ──────────────────────────────────────────────────────
    http_response_code(200);
    echo json_encode([
        'success'   => true,
        'processed' => $processed,
        'failed'    => $failed,
        'results'   => $results,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[webhook_estados] Error: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
