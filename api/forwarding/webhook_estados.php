<?php

/**
 * POST /api/forwarding/webhook_estados.php
 *
 * Webhook para recibir actualizaciones de estado de órdenes desde RutaEx.
 * RutaEx hace POST con el JSON de actualización; nosotros validamos, mapeamos
 * el estado a nuestro catálogo interno y actualizamos la tabla `pedidos`.
 *
 * Autenticación: Bearer token en header Authorization.
 * El token debe coincidir con el `webhook_secret` configurado en el proveedor
 * LogisPro (forwarding_providers.credentials->webhook_secret).
 *
 * JSON esperado (enviado por RutaEx):
 * {
 *   "customersId":   54,
 *   "auditUser":     "rutaexmex.api",
 *   "state":         "Reprogramado",
 *   "substate":      "Nuevo Intento",
 *   "dateToReceive": "2026-05-04",       // obligatorio si state = "Reprogramado"
 *   "notes":         "Texto libre...",   // opcional
 *   "ordersNumbers": [
 *     { "orderNumber": "123987123456" },
 *     { "orderNumber": "414141" }
 *   ]
 * }
 *
 * Mapeo de estados RutaEx → estados_pedidos.id:
 *   "En ruta o proceso"        → 2   (En ruta o proceso)
 *   "Pendiente recolección"    → 11  (Pendiente recolección por mensajería)
 *   "Recolectado por mensajería" → 12 (Recolectado por mensajería)
 *   "Traslado a punto"         → 13  (Traslado a punto de distribución)
 *   "Entregado"                → 3   (Entregado)
 *   "Entregado-liquidado"      → 14  (Entregado – liquidado)
 *   "Reprogramado"             → 4   (Reprogramado)
 *   "Domicilio cerrado"        → 5   (Domicilio cerrado)
 *   "No hay quien reciba"      → 6   (No hay quien reciba en domicilio)
 *   "Devuelto"                 → 7   (Devuelto)
 *   "Domicilio no encontrado"  → 8   (Domicilio no encontrado)
 *   "Rechazado"                → 9   (Rechazado)
 *   "No puede pagar recaudo"   → 10  (No puede pagar recaudo)
 *
 * Nota: El campo "substate" no tiene tabla propia en el sistema; su valor
 * se almacena como parte de las observaciones en pedidos_historial_estados.
 *
 * Response:
 * {
 *   "success":   true,
 *   "processed": 2,
 *   "failed":    0,
 *   "results": [
 *     { "orderNumber": "123987123456", "updated": true },
 *     { "orderNumber": "414141",       "updated": false, "error": "Orden no encontrada" }
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

// ─── Mapeo RutaEx state+substate → estados_pedidos.id ───────────────────────────
//
// RutaEx envía en el campo "state" el VALOR de su diccionario homologado,
// NO el nombre interno. Los 4 valores posibles son:
//   "Confirmado"   → estados en tránsito (substate siempre "Confirmado")
//   "Entregado"    → entrega exitosa     (substate "Efectiva")
//   "Reprogramado" → nuevo intento       (substate "Nuevo intento")
//   "No entregado" → fallo de entrega    (substate diferencia el motivo)
//
// Estructura: RUTAEX_STATE_MAP[state][substate_normalizado] = id_estado
//
const RUTAEX_STATE_MAP = [
    // ── Confirmado ────────────────────────────────────────────────────────────
    // Agrupa: En ruta, Pendiente recolección, Recolectado, Traslado a punto.
    // El substate siempre es "Confirmado" → mapeamos a "En ruta o proceso" (2)
    // como estado genérico de tránsito.
    'Confirmado' => [
        'confirmado' => 2,  // En ruta o proceso (genérico)
    ],

    // ── Entregado ─────────────────────────────────────────────────────────────
    'Entregado' => [
        'efectiva'  => 3,   // Entregado
    ],

    // ── Reprogramado ──────────────────────────────────────────────────────────
    'Reprogramado' => [
        'nuevo intento' => 4,  // Reprogramado
    ],

    // ── No entregado ──────────────────────────────────────────────────────────
    // El substate diferencia el motivo exacto del fallo.
    'No entregado' => [
        'en lugar, sin contacto' => 5,   // Domicilio cerrado
        'fuera de la ciudad'     => 6,   // No hay quien reciba en domicilio
        'no desea producto'      => 7,   // Devuelto / Rechazado → usar Devuelto
        'direccion errada'       => 8,   // Domicilio no encontrado
        'sin dinero'             => 10,  // No puede pagar recaudo
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

    $state     = trim($body['state']);
    $substate  = trim($body['substate']);
    $notes     = trim($body['notes'] ?? '');
    $auditUser = trim($body['auditUser'] ?? 'rutaex');

    // ─── 4. Mapear estado RutaEx → ID interno ─────────────────────────────
    // Normalizar (trim + lowercase) para comparación robusta
    $stateKey    = $state;
    $substateKey = mb_strtolower(trim($substate), 'UTF-8');

    if (!array_key_exists($stateKey, RUTAEX_STATE_MAP)) {
        http_response_code(400);
        echo json_encode([
            'success'      => false,
            'message'      => "Estado no reconocido: '$state'.",
            'valid_states' => array_keys(RUTAEX_STATE_MAP),
        ]);
        exit;
    }

    $substateMap = RUTAEX_STATE_MAP[$stateKey];

    if (!array_key_exists($substateKey, $substateMap)) {
        // Fallback: si solo hay un valor en el mapa (ej. Confirmado/Entregado)
        // usarlo directamente sin importar el substate recibido
        if (count($substateMap) === 1) {
            $idEstadoNuevo = reset($substateMap);
        } else {
            http_response_code(400);
            echo json_encode([
                'success'         => false,
                'message'         => "Substate '$substate' no reconocido para state '$state'.",
                'valid_substates' => array_keys($substateMap),
            ]);
            exit;
        }
    } else {
        $idEstadoNuevo = $substateMap[$substateKey];
    }

    // Construir texto de observaciones:
    // Formato: "RutaEx [auditUser] → state / substate[: notes]"
    $observaciones = "RutaEx [{$auditUser}] → {$state} / {$substate}";
    if ($notes !== '') {
        $observaciones .= ": {$notes}";
    }

    // ─── 5. Fecha de entrega (obligatoria solo en Reprogramado) ───────────
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

    // ─── 6. Procesar cada orden ────────────────────────────────────────────
    $db        = (new Conexion())->conectar();
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

        // Inyectar contexto para el trigger after_pedido_update_estado:
        // El trigger usa @current_user_id y @current_observaciones.
        // NULL = Sistema (webhook automático), sin usuario humano.
        $db->exec("SET @current_user_id = NULL, @current_observaciones = " .
            $db->quote($observaciones));

        $sql = "UPDATE pedidos SET " . implode(', ', $sets) . " WHERE id = :id";
        $upd = $db->prepare($sql);
        $upd->execute($params);
        // El trigger se encarga de insertar en pedidos_historial_estados.

        $results[] = ['orderNumber' => $orderNumber, 'updated' => true];
        $processed++;

        error_log("Webhook RutaEx: orden {$orderNumber} → estado {$idEstadoNuevo} ({$state} / {$substate})");
    }

    // ─── 7. Respuesta ──────────────────────────────────────────────────────
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
