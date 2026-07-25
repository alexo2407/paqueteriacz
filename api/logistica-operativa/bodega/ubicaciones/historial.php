<?php
/**
 * GET /api/logistica-operativa/bodega/ubicaciones/historial?id_pedido=N
 *
 * Devuelve el historial completo de movimientos físicos de un paquete.
 *
 * Headers requeridos:
 *   Authorization: Bearer <JWT>
 *
 * Query string:
 *   id_pedido=<int>
 *
 * Respuestas:
 *   200  → historial (puede ser array vacío si nunca tuvo ubicación)
 *   400  → parámetro id_pedido faltante o inválido
 *   401  → no autenticado
 *   403  → módulo deshabilitado o shadow mode inactivo
 *   500  → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../controlador/logistica_operativa/BodegaUbicacionController.php';

    $controller = new BodegaUbicacionController();
    $controller->historial();
} catch (Throwable $e) {
    error_log('[api/bodega/ubicaciones/historial] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
