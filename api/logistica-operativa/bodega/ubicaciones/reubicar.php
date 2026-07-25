<?php
/**
 * POST /api/logistica-operativa/bodega/ubicaciones/reubicar
 *
 * Reubica un paquete a otra ubicación dentro de la misma bodega.
 *
 * Headers requeridos:
 *   Authorization: Bearer <JWT>
 *   Content-Type:  application/json
 *
 * Body JSON:
 *   { "id_pedido": 1, "id_ubicacion_destino": 2, "motivo": "..." }
 *
 * Respuestas:
 *   200  → reubicado o sin cambio (misma ubicación)
 *   400  → datos inválidos
 *   401  → no autenticado
 *   403  → módulo deshabilitado o shadow mode inactivo
 *   404  → pedido o ubicación no encontrada
 *   422  → traslado entre bodegas no permitido u otra regla de negocio
 *   500  → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../controlador/logistica_operativa/BodegaUbicacionController.php';

    $controller = new BodegaUbicacionController();
    $controller->reubicar();
} catch (Throwable $e) {
    error_log('[api/bodega/ubicaciones/reubicar] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
