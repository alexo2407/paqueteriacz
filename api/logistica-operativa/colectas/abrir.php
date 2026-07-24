<?php
/**
 * POST /api/logistica-operativa/colectas/abrir
 *
 * Abre una nueva colecta.
 *
 * Headers requeridos:
 *   Authorization: Bearer <JWT>
 *   Content-Type:  application/json
 *
 * Body JSON:
 *   { "id_cliente": 1, "fecha": "2026-07-24", "turno": "MANANA" }
 *
 * El operador se obtiene del token JWT, nunca del body.
 *
 * Respuestas:
 *   201  → colecta creada, { success: true, data: { id_colecta, cantidad_esperada, pedidos_ids } }
 *   400  → datos inválidos
 *   401  → no autenticado
 *   403  → módulo deshabilitado
 *   409  → colecta duplicada
 *   422  → regla de negocio
 *   500  → error interno (sin detalles sensibles)
 */

declare(strict_types=1);

// Cabeceras tempranas para garantizar JSON incluso en errores fatales
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../controlador/logistica_operativa/ColectaController.php';

    $controller = new ColectaController();
    $controller->abrir();
} catch (Throwable $e) {
    error_log('[api/colectas/abrir] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
