<?php

/**
 * POST /api/pedidos/reprogramar
 *
 * Reprograma un pedido: cambia su estado al indicado (por defecto ID 4 = Reprogramado),
 * actualiza la nueva fecha de entrega y registra el motivo en el historial de estados.
 *
 * Requiere autenticación Bearer JWT.
 *
 * ─── Body JSON (application/json) ─────────────────────────────────────────
 *  numero_orden   string   (requerido)  Número de orden del pedido
 *  fecha_entrega  string   (requerido)  Nueva fecha de entrega (YYYY-MM-DD)
 *  id_estado      int      (opcional)   Estado destino (default: 4 = Reprogramado)
 *  id_cliente     int      (opcional)   ID del cliente a asignar al pedido
 *  motivo         string   (opcional)   Razón de la reprogramación
 * ───────────────────────────────────────────────────────────────────────────
 *
 * ─── Response (éxito) ──────────────────────────────────────────────────────
 * {
 *   "success": true,
 *   "message": "Pedido 81154737 reprogramado para el 2026-05-20.",
 *   "data": {
 *     "numero_orden":   "81154737",
 *     "id_estado":      4,
 *     "fecha_entrega":  "2026-05-20",
 *     "motivo":         "Cliente ausente en primer intento",
 *     "reprogramado_en": "2026-05-05 14:30:00"
 *   }
 * }
 * ───────────────────────────────────────────────────────────────────────────
 *
 * ─── Errores posibles ──────────────────────────────────────────────────────
 *  400  Faltan campos requeridos / formato de fecha inválido / estado inválido
 *  401  Token ausente o inválido
 *  403  Sin permiso sobre el pedido
 *  404  Pedido no encontrado
 *  409  El pedido ya fue entregado
 *  500  Error interno
 * ───────────────────────────────────────────────────────────────────────────
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Use POST.']);
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

    // Exponer rol globalmente para que actualizarEstadoCliente() lo use
    $GLOBALS['API_USER_ROLE'] = $authUserRole;

    // ── 2. Leer y validar body JSON ────────────────────────────────────────
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) {
        responder(false, 'El body debe ser JSON válido.', null, 400);
    }

    $numeroOrden  = trim($body['numero_orden']  ?? '');
    $fechaEntrega = trim($body['fecha_entrega'] ?? '');
    $motivo       = trim($body['motivo']        ?? '');

    // id_estado: por defecto 4 (Reprogramado); puede enviarse otro valor explícito
    $idEstado = isset($body['id_estado']) && is_numeric($body['id_estado'])
        ? (int)$body['id_estado']
        : 4;

    // id_cliente: opcional — reasigna el cliente del pedido
    $idCliente = isset($body['id_cliente']) && is_numeric($body['id_cliente'])
        ? (int)$body['id_cliente']
        : null;

    // ── 3. Validaciones de campos ──────────────────────────────────────────
    if ($numeroOrden === '') {
        responder(false, 'El campo numero_orden es requerido.', null, 400);
    }

    if ($fechaEntrega === '') {
        responder(false, 'El campo fecha_entrega es requerido (YYYY-MM-DD).', null, 400);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEntrega)) {
        responder(false, 'Formato de fecha_entrega inválido. Use YYYY-MM-DD.', null, 400);
    }

    if ($idEstado <= 0) {
        responder(false, 'El campo id_estado debe ser un entero positivo.', null, 400);
    }

    // Solo se permiten los estados del 1 al 13 y del 15 al 17
    $estadosPermitidos = array_merge(range(1, 13), range(15, 17));
    if (!in_array($idEstado, $estadosPermitidos, true)) {
        responder(false, "El id_estado {$idEstado} no está permitido. Use un valor entre 1-13 o 15-17.", null, 400);
    }

    // Verificar que la fecha no sea en el pasado
    $hoy = (new DateTime('today', new DateTimeZone('America/Managua')))->format('Y-m-d');
    if ($fechaEntrega < $hoy) {
        responder(false, 'La fecha_entrega no puede ser en el pasado.', null, 400);
    }

    // ── 4. Ejecutar reprogramación via modelo ──────────────────────────────
    $resultado = PedidosModel::reprogramarPedido(
        $numeroOrden,
        $authUserId,
        $authUserRole,
        $fechaEntrega,
        $idEstado,
        $motivo !== '' ? $motivo : null,
        $idCliente
    );

    // Mapear code de error a HTTP status
    if (!$resultado['success']) {
        $httpCode = $resultado['code'] ?? 400;
        $extra    = isset($resultado['detail']) ? ['detail' => $resultado['detail']] : [];
        responder(false, $resultado['message'], empty($extra) ? null : $extra, (int)$httpCode);
    }

    // ── 5. Respuesta exitosa ───────────────────────────────────────────────
    $tz           = new DateTimeZone('America/Managua');
    $ahoraLocal   = (new DateTime('now', $tz))->format('Y-m-d H:i:s');
    $nombreEstado = $resultado['nombre_estado'] ?? 'actualizado';

    // Mensaje dinámico: si es Reprogramado (ID 4) incluye la nueva fecha;
    // para cualquier otro estado usa el nombre real de la BD
    if ($idEstado === 4) {
        $msg = "Pedido {$numeroOrden} reprogramado para el {$fechaEntrega}.";
    } else {
        $msg = "Pedido {$numeroOrden} actualizado al estado '{$nombreEstado}'.";
    }

    responder(true, $msg, [
        'numero_orden'    => $numeroOrden,
        'id_estado'       => $idEstado,
        'estado'          => $nombreEstado,
        'id_cliente'      => $idCliente,
        'fecha_entrega'   => $fechaEntrega,
        'motivo'          => $motivo !== '' ? $motivo : null,
        'reprogramado_en' => $ahoraLocal,
    ], 200);
} catch (Throwable $e) {
    error_log('[api/pedidos/reprogramar] Error: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    responder(false, 'Error interno del servidor.', null, 500);
}
