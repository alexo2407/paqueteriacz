<?php
/**
 * POST /api/logistica-operativa/bodega/ubicaciones/asignar
 *
 * Asigna una ubicación física a una recepción en estado RECIBIDO.
 *
 * Headers requeridos:
 *   Authorization: Bearer <JWT>
 *   Content-Type:  application/json
 *
 * Body JSON:
 *   { "id_pedido": 1, "id_recepcion": 1, "id_ubicacion": 1, "motivo": "..." }
 *
 * Respuestas:
 *   200  → ubicación asignada
 *   400  → datos inválidos
 *   401  → no autenticado
 *   403  → módulo deshabilitado o shadow mode inactivo
 *   404  → pedido, recepción o ubicación no encontrada
 *   409  → paquete ya ubicado
 *   422  → regla de negocio
 *   500  → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../controlador/logistica_operativa/BodegaUbicacionController.php';

    $controller = new BodegaUbicacionController();
    $controller->asignar();
} catch (Throwable $e) {
    error_log('[api/bodega/ubicaciones/asignar] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
