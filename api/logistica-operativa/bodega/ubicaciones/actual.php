<?php
/**
 * GET /api/logistica-operativa/bodega/ubicaciones/actual?id_pedido=N
 *
 * Devuelve la ubicación física actual de un paquete.
 *
 * Headers requeridos:
 *   Authorization: Bearer <JWT>
 *
 * Query string:
 *   id_pedido=<int>
 *
 * Respuestas:
 *   200  → ubicación activa encontrada
 *   400  → parámetro id_pedido faltante o inválido
 *   401  → no autenticado
 *   403  → módulo deshabilitado o shadow mode inactivo
 *   404  → pedido sin ubicación activa (UBICACION_ACTUAL_NO_ENCONTRADA)
 *   500  → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../controlador/logistica_operativa/BodegaUbicacionController.php';

    $controller = new BodegaUbicacionController();
    $controller->actual();
} catch (Throwable $e) {
    error_log('[api/bodega/ubicaciones/actual] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
