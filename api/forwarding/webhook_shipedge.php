<?php
/**
 * Endpoint receptor de Webhooks de ShipEdge OMS v41
 *
 * Ruta: POST /api/forwarding/webhook_shipedge.php
 *
 * Responsabilidades:
 *  - Recibir POST JSON por HTTPS (validando HTTPS en producción).
 *  - Validar autenticación entrante mediante encabezado (X-ShipEdge-Secret, X-Webhook-Secret,
 *    X-Token, Authorization: Bearer, Key) o secreto configurado en la cuenta ShipEdge,
 *    además de validar AccountID cuando venga presente.
 *  - Guardar el evento original en forwarding_webhook_events (sin credenciales expuestas).
 *  - Evitar procesar eventos duplicados (idempotencia).
 *  - Homologar estado externo -> estado interno mediante forwarding_status_mapping
 *    (nunca interpretando "shipped" como "Entregado").
 *  - Actualizar guía, tracking y transportista cuando existan.
 *  - Conservar historial de cambios y disparar webhooks salientes del sistema.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modelo/conexion.php';
require_once __DIR__ . '/../../modelo/forwarding.php';
require_once __DIR__ . '/../../services/ShipEdgeWebhookAndReconcileService.php';

// 1. Validar método HTTP POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Use POST JSON.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Validar HTTPS en entorno de producción
$appEnv = defined('APP_ENV') ? APP_ENV : (getenv('APP_ENV') ?: 'development');
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
);
if ($appEnv === 'production' && !$isHttps) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Se requiere conexión segura HTTPS para el webhook de ShipEdge.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Leer y validar cuerpo JSON
$rawPayload = file_get_contents('php://input');
if ($rawPayload === false || trim($rawPayload) === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Cuerpo de solicitud vacío.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode($rawPayload, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Formato JSON inválido.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Extraer encabezados HTTP
$headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
foreach ($_SERVER as $sKey => $sVal) {
    if (strpos($sKey, 'HTTP_') === 0) {
        $hName = str_replace('_', '-', substr($sKey, 5));
        if (!isset($headers[$hName])) {
            $headers[$hName] = $sVal;
        }
    }
}

try {
    // 5. Obtener proveedores ShipEdge configurados y validar autenticación + cuenta
    $providers = ForwardingModel::obtenerProveedoresPorSlug('shipedge');
    if (empty($providers)) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'No hay proveedores ShipEdge configurados en el sistema.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $authenticatedProvider = ShipEdgeWebhookAndReconcileService::autenticarProveedorWebhook($headers, $payload, $providers);
    if ($authenticatedProvider === null) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'No autorizado: Secreto de webhook o AccountID inválido.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 6. Procesar el evento (guardar raw, verificar idempotencia, homologar estado, actualizar guía/tracking y disparar webhooks salientes)
    $db = (new Conexion())->conectar();
    $result = ShipEdgeWebhookAndReconcileService::procesarEventoOrden(
        $db,
        $authenticatedProvider,
        $payload,
        $rawPayload,
        'webhook'
    );

    http_response_code($result['http_code'] ?? ($result['success'] ? 200 : 422));
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
    error_log('webhook_shipedge.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno al procesar webhook de ShipEdge.',
    ], JSON_UNESCAPED_UNICODE);
}

