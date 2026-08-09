<?php
/**
 * GET /api/logistica-operativa/bodega/pedidos/buscar?q=...
 *
 * Busca un pedido por ID, número de orden o número de guía/tracking.
 *
 * Criterios soportados (en orden de prioridad):
 *   1. ID numérico exacto (si q es entero puro).
 *   2. Número de orden exacto (campo numero_orden).
 *
 * Devuelve datos mínimos del pedido sin información sensible completa:
 *   - id, numero_orden, destinatario, telefono (enmascarado), municipio,
 *     estado actual, fecha_ingreso.
 *
 * Seguridad:
 *   - Requiere autenticación JWT y autorización.
 *   - Requiere módulo habilitado y shadow mode.
 *   - No modifica datos.
 *   - Teléfono enmascarado: solo últimos 4 dígitos visibles.
 *   - No expone DSN, contraseñas, SQL ni traces.
 *
 * Respuestas:
 *   200 → { success: true, data: { pedido } }
 *   400 → consulta vacía o inválida
 *   401 → no autenticado
 *   403 → sin autorización o módulo deshabilitado
 *   404 → pedido no encontrado
 *   500 → error interno
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../../../controlador/logistica_operativa/BodegaUbicacionController.php';
    require_once __DIR__ . '/../../../../modelo/conexion.php';

    $controller = new BodegaUbicacionController();
    $controller->aplicarHeaders('GET, OPTIONS');
    $controller->requerirMetodo('GET');
    $controller->verificarModulo();
    $usuario = $controller->autenticar();
    $controller->verificarAutorizacion($usuario);

    // Validar parámetro de búsqueda
    $q = trim($_GET['q'] ?? '');
    if ($q === '') {
        $controller->error('MISSING_PARAM', 'El parámetro de búsqueda q es requerido.', 400);
    }
    if (mb_strlen($q) > 120) {
        $controller->error('PARAM_TOO_LONG', 'El parámetro q es demasiado largo.', 400);
    }

    $db = $controller->crearConexion();

    // ── Estrategia de búsqueda ────────────────────────────────────────────────
    $pedido = null;

    // 1. Por ID numérico
    if (ctype_digit($q) && (int) $q > 0) {
        $stmt = $db->prepare(
            'SELECT p.id, p.numero_orden, p.destinatario, p.telefono,
                    p.municipalitiesName, p.departmentName,
                    p.id_estado, ep.nombre_estado, p.fecha_ingreso,
                    p.id_cliente
               FROM pedidos p
               LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
              WHERE p.id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => (int) $q]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $pedido = $row;
        }
    }

    // 2. Por número de orden (exacto)
    if ($pedido === null) {
        $stmt = $db->prepare(
            'SELECT p.id, p.numero_orden, p.destinatario, p.telefono,
                    p.municipalitiesName, p.departmentName,
                    p.id_estado, ep.nombre_estado, p.fecha_ingreso,
                    p.id_cliente
               FROM pedidos p
               LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
              WHERE p.numero_orden = :num
              LIMIT 1'
        );
        $stmt->execute([':num' => $q]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $pedido = $row;
        }
    }

    if ($pedido === null) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'code'    => 'PEDIDO_NO_ENCONTRADO',
            'message' => 'No se encontró ningún pedido con ese criterio de búsqueda.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Enmascarar teléfono ───────────────────────────────────────────────────
    $tel = (string) ($pedido['telefono'] ?? '');
    $telMascarado = '';
    if ($tel !== '') {
        $solo  = preg_replace('/\D/', '', $tel);
        $len   = strlen($solo);
        $telMascarado = $len > 4
            ? str_repeat('*', $len - 4) . substr($solo, -4)
            : str_repeat('*', $len);
    }

    // ── Municipio / dirección ─────────────────────────────────────────────────
    $municipio = $pedido['municipalitiesName']
        ?? $pedido['municipio']
        ?? null;

    $resultado = [
        'id'            => (int) $pedido['id'],
        'numero_orden'  => (string) ($pedido['numero_orden'] ?? ''),
        'destinatario'  => (string) ($pedido['destinatario'] ?? ''),
        'telefono'      => $telMascarado,
        'municipio'     => $municipio !== null ? (string) $municipio : null,
        'departamento'  => $pedido['departmentName'] !== null
            ? (string) $pedido['departmentName']
            : null,
        'id_estado'     => (int) ($pedido['id_estado'] ?? 0),
        'estado_nombre' => (string) ($pedido['nombre_estado'] ?? ''),
        'fecha_ingreso' => (string) ($pedido['fecha_ingreso'] ?? ''),
        'id_cliente'    => (int) ($pedido['id_cliente'] ?? 0),
    ];

    http_response_code(200);
    echo json_encode(
        ['success' => true, 'data' => $resultado],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

} catch (Throwable $e) {
    error_log('[api/bodega/pedidos/buscar] Uncaught: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'code' => 'INTERNAL_ERROR', 'message' => 'Error interno del servidor.']);
}
