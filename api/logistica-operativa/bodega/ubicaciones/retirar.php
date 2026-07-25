<?php
/**
 * POST /api/logistica-operativa/bodega/ubicaciones/retirar
 *
 * Retira un paquete de su ubicación actual.
 * Operación idempotente: repetir el retiro no genera movimientos duplicados.
 *
 * Headers requeridos:
 *   Authorization: Bearer <JWT>
 *   Content-Type:  application/json
 *
 * Body JSON:
 *   { "id_pedido": 1, "motivo": "..." }
 *
 * Respuestas:
 *   200  → retirado (o idempotente)
 *   400  → datos inválidos
 *   401  → no autenticado
 *   403  → módulo deshabilitado o shadow mode inactivo
 *   404  → pedido no encontrado o sin ubicación activa
 *   500  → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../controlador/logistica_operativa/BodegaUbicacionController.php';

    $controller = new BodegaUbicacionController();
    $controller->retirar();
} catch (Throwable $e) {
    error_log('[api/bodega/ubicaciones/retirar] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
