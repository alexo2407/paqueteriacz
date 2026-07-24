<?php
/**
 * POST /api/logistica-operativa/colectas/cerrar
 *
 * Cierra y concilia una colecta abierta.
 *
 * Headers requeridos:
 *   Authorization: Bearer <JWT>
 *   Content-Type:  application/json
 *
 * Body JSON:
 *   { "id_colecta": 1 }
 *
 * El id_operador se obtiene del token JWT, nunca del body.
 *
 * Respuestas:
 *   200  → colecta conciliada, { success: true, data: { colecta: {...}, conteos: {...} } }
 *   400  → datos inválidos
 *   401  → no autenticado
 *   403  → módulo deshabilitado
 *   404  → colecta no encontrada
 *   409  → colecta ya está cerrada o no está ABIERTA
 *   500  → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../controlador/logistica_operativa/ColectaController.php';

    $controller = new ColectaController();
    $controller->cerrar();
} catch (Throwable $e) {
    error_log('[api/colectas/cerrar] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
