<?php
/**
 * POST /api/logistica-operativa/bodega/recepciones/registrar
 *
 * Registra la recepción física de un paquete en bodega.
 *
 * Headers requeridos:
 *   Authorization: Bearer <JWT>
 *   Content-Type:  application/json
 *
 * Body JSON:
 *   {
 *     "uuid": "UUID-VALIDO",
 *     "id_pedido": 1,
 *     "id_bodega": 1,
 *     "id_ubicacion": null,
 *     "id_escaneo": null,
 *     "tipo_recepcion": "COLECTA",
 *     "recibido_at": "2026-07-24 10:30:00",
 *     "observacion": "Recepción física"
 *   }
 *
 * El id_operador se obtiene del token JWT, nunca del body.
 *
 * Respuestas:
 *   201  → recepción creada, { success: true, data: { idempotente, id_recepcion, estado } }
 *   200  → UUID idempotente, ya existía
 *   400  → datos inválidos
 *   401  → no autenticado
 *   403  → módulo deshabilitado o shadow mode inactivo
 *   404  → pedido, bodega o ubicación no encontrada
 *   409  → recepción activa existente
 *   422  → regla de negocio inválida
 *   500  → error interno (sin detalles sensibles)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../controlador/logistica_operativa/BodegaUbicacionController.php';

    $controller = new BodegaUbicacionController();
    $controller->registrar();
} catch (Throwable $e) {
    error_log('[api/bodega/recepciones/registrar] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
