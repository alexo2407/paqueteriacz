<?php
/**
 * POST /api/logistica-operativa/colectas/escanear
 *
 * Registra un escaneo físico (idempotente por UUID).
 *
 * Headers requeridos:
 *   Authorization: Bearer <JWT>
 *   Content-Type:  application/json
 *
 * Body JSON:
 *   {
 *     "uuid":         "UUID-v4",
 *     "id_colecta":   1,
 *     "id_pedido":    1,
 *     "tipo_evento":  "COLECTA_RECEPCION",
 *     "qr_hash":      "64-hex-chars",
 *     "dispositivo":  "scanner-01",
 *     "escaneado_at": "2026-07-24 10:00:00",
 *     "metadata_json": {}
 *   }
 *
 * El id_operador se obtiene del token JWT, nunca del body.
 *
 * Respuestas:
 *   200  → escaneo registrado / idempotente, { success: true, data: { idempotente, id_escaneo, resultado_pedido } }
 *   400  → datos inválidos
 *   401  → no autenticado
 *   403  → módulo deshabilitado
 *   404  → colecta o pedido no encontrado
 *   409  → colecta no está ABIERTA
 *   422  → formato UUID o qr_hash inválido
 *   500  → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../controlador/logistica_operativa/ColectaController.php';

    $controller = new ColectaController();
    $controller->escanear();
} catch (Throwable $e) {
    error_log('[api/colectas/escanear] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
