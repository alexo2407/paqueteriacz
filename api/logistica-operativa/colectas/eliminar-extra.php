<?php
/**
 * POST /api/logistica-operativa/colectas/eliminar-extra
 *
 * Elimina un paquete registrado como EXTRA de una colecta abierta.
 *
 * Headers requeridos:
 *   Authorization: Bearer <JWT> o sesión web activa
 *   Content-Type:  application/json
 *
 * Body JSON:
 *   { "id_colecta": 1, "id_pedido": 100 }
 *
 * Respuestas:
 *   200  → extra eliminado, { success: true, data: { colecta: {...}, conteos: {...} } }
 *   400  → datos inválidos
 *   401  → no autenticado
 *   404  → colecta o pedido extra no encontrado
 *   500  → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../controlador/logistica_operativa/ColectaController.php';

    $controller = new ColectaController();
    $controller->eliminarExtra();
} catch (Throwable $e) {
    error_log('[api/colectas/eliminar-extra] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
