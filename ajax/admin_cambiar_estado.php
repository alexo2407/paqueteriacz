<?php
/**
 * ajax/admin_cambiar_estado.php
 *
 * Herramienta exclusiva de Super Administrador para cambiar el estado de uno
 * o varios pedidos por número de orden y reconstruir/limpiar su historial de estados.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/permissions.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/auditoria.php';

start_secure_session();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['registrado'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado.']);
    exit;
}

if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Exclusivo para Administradores.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$jsonBody = !empty($rawInput) ? json_decode($rawInput, true) : [];
$input    = is_array($jsonBody) && !empty($jsonBody) ? $jsonBody : $_POST;
$action   = $input['action'] ?? $_GET['action'] ?? '';

/**
 * Extrae una lista limpia y única de números de orden desde un texto o arreglo.
 *
 * @param mixed $raw
 * @return array<int,string>
 */
function parseNumerosOrden($raw): array
{
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $items = preg_split('/[\s,;]+/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY);
    }

    $clean = [];
    foreach ($items as $item) {
        $val = trim((string)$item, " \t\n\r\0\x0B#");
        if ($val !== '') {
            $clean[$val] = $val;
        }
    }
    return array_values($clean);
}

try {
    $db = (new Conexion())->conectar();

    // =========================================================================
    // ACCIÓN 1: PREVISUALIZAR PEDIDOS E HISTORIAL ACTUAL
    // =========================================================================
    if ($action === 'preview') {
        $numeros = parseNumerosOrden($input['numeros_orden'] ?? $_GET['numeros_orden'] ?? '');
        if (empty($numeros)) {
            echo json_encode(['success' => false, 'message' => 'Ingresa al menos un número de orden.']);
            exit;
        }

        if (count($numeros) > 100) {
            echo json_encode(['success' => false, 'message' => 'Máximo 100 números de orden por consulta.']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($numeros), '?'));
        $sql = "SELECT 
                    p.id,
                    p.numero_orden,
                    p.destinatario,
                    p.id_estado,
                    ep.nombre_estado,
                    p.fecha_ingreso,
                    uc.nombre AS cliente_nombre,
                    up.nombre AS proveedor_nombre
                FROM pedidos p
                LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
                LEFT JOIN usuarios uc ON uc.id = p.id_cliente
                LEFT JOIN usuarios up ON up.id = p.id_proveedor
                WHERE CAST(p.numero_orden AS CHAR) IN ($placeholders)
                ORDER BY p.id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($numeros);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener el historial actual de cada pedido encontrado
        $stmtHist = $db->prepare("
            SELECT 
                h.id,
                h.id_estado_anterior,
                ea.nombre_estado AS estado_anterior,
                h.id_estado_nuevo,
                en.nombre_estado AS estado_nuevo,
                h.observaciones,
                h.created_at
            FROM pedidos_historial_estados h
            LEFT JOIN estados_pedidos ea ON ea.id = h.id_estado_anterior
            LEFT JOIN estados_pedidos en ON en.id = h.id_estado_nuevo
            WHERE h.id_pedido = :id_pedido
            ORDER BY h.created_at DESC, h.id DESC
        ");

        $encontradosMap = [];
        foreach ($pedidos as &$ped) {
            $stmtHist->execute([':id_pedido' => (int)$ped['id']]);
            $ped['historial'] = $stmtHist->fetchAll(PDO::FETCH_ASSOC);
            $encontradosMap[(string)$ped['numero_orden']] = true;
        }
        unset($ped);

        $noEncontrados = [];
        foreach ($numeros as $num) {
            if (!isset($encontradosMap[(string)$num])) {
                $noEncontrados[] = $num;
            }
        }

        echo json_encode([
            'success'        => true,
            'pedidos'        => $pedidos,
            'no_encontrados' => $noEncontrados,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // ACCIÓN 2: APLICAR CAMBIO DE ESTADO Y AJUSTE DE HISTORIAL
    // =========================================================================
    if ($action === 'apply') {
        $numeros        = parseNumerosOrden($input['numeros_orden'] ?? '');
        $idEstadoNuevo  = (int)($input['id_estado_nuevo'] ?? 0);
        $modoHistorial  = trim((string)($input['modo_historial'] ?? 'limpio_bodega'));
        $observaciones  = trim((string)($input['observaciones'] ?? ''));
        $dispararWebhook = !empty($input['disparar_webhook']);
        $adminUserId    = (int)($_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 1);

        if (empty($numeros)) {
            echo json_encode(['success' => false, 'message' => 'Ingresa al menos un número de orden.']);
            exit;
        }

        if ($idEstadoNuevo <= 0) {
            echo json_encode(['success' => false, 'message' => 'Selecciona un estado válido.']);
            exit;
        }

        // Verificar que el estado destino exista en estados_pedidos
        $stmtEstado = $db->prepare('SELECT id, nombre_estado FROM estados_pedidos WHERE id = :id LIMIT 1');
        $stmtEstado->execute([':id' => $idEstadoNuevo]);
        $estadoRow = $stmtEstado->fetch(PDO::FETCH_ASSOC);

        if (!$estadoRow) {
            echo json_encode(['success' => false, 'message' => 'El estado seleccionado no existe.']);
            exit;
        }

        $nombreEstadoNuevo = $estadoRow['nombre_estado'];
        $obsFinal = $observaciones !== '' ? $observaciones : $nombreEstadoNuevo;

        $db->beginTransaction();

        $placeholders = implode(',', array_fill(0, count($numeros), '?'));
        $stmtPedidos = $db->prepare("
            SELECT p.id, p.numero_orden, p.id_estado, p.id_cliente, p.id_proveedor, p.fecha_ingreso,
                   ep.nombre_estado AS estado_anterior_nombre
            FROM pedidos p
            LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
            WHERE CAST(p.numero_orden AS CHAR) IN ($placeholders)
            FOR UPDATE
        ");
        $stmtPedidos->execute($numeros);
        $pedidos = $stmtPedidos->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pedidos)) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'No se encontró ningún pedido con los números indicados.']);
            exit;
        }

        // Configurar variables de sesión para el trigger after_pedido_update_estado
        $stmtVars = $db->prepare('SET @current_user_id = :uid, @current_observaciones = :obs, @current_created_at = NULL');
        $stmtVars->execute([
            ':uid' => $adminUserId,
            ':obs' => $obsFinal,
        ]);

        $stmtUpdPedido = $db->prepare('UPDATE pedidos SET id_estado = :id_estado, updated_at = NOW() WHERE id = :id');
        $stmtDelHist   = $db->prepare('DELETE FROM pedidos_historial_estados WHERE id_pedido = :id_pedido');
        $stmtInsHist   = $db->prepare('
            INSERT INTO pedidos_historial_estados (
                id_pedido,
                id_estado_anterior,
                id_estado_nuevo,
                id_usuario,
                observaciones,
                created_at
            ) VALUES (
                :id_pedido,
                :id_estado_anterior,
                :id_estado_nuevo,
                :id_usuario,
                :observaciones,
                :created_at
            )
        ');

        $actualizados = [];

        foreach ($pedidos as $ped) {
            $idPedido       = (int)$ped['id'];
            $estadoAnterior = (int)$ped['id_estado'];
            $usuarioHist    = (int)($ped['id_proveedor'] ?: ($ped['id_cliente'] ?: $adminUserId));
            $fechaIngreso   = !empty($ped['fecha_ingreso']) ? $ped['fecha_ingreso'] : date('Y-m-d H:i:s');

            // 1. Actualizar estado en tabla pedidos
            $stmtUpdPedido->execute([
                ':id_estado' => $idEstadoNuevo,
                ':id'        => $idPedido,
            ]);

            // 2. Gestionar historial según modo seleccionado
            if ($modoHistorial === 'limpio_bodega') {
                // Borrar historial previo (incluyendo el registro que haya insertado el trigger)
                $stmtDelHist->execute([':id_pedido' => $idPedido]);

                // Insertar paso 1: En bodega (1) con fecha de ingreso del pedido
                $stmtInsHist->execute([
                    ':id_pedido'          => $idPedido,
                    ':id_estado_anterior' => 1,
                    ':id_estado_nuevo'    => 1,
                    ':id_usuario'         => $usuarioHist,
                    ':observaciones'      => 'Ingreso a bodega',
                    ':created_at'         => $fechaIngreso,
                ]);

                // Si el estado nuevo es distinto de En bodega (1), insertar paso 2: En bodega (1) -> Nuevo Estado
                if ($idEstadoNuevo !== 1) {
                    $stmtInsHist->execute([
                        ':id_pedido'          => $idPedido,
                        ':id_estado_anterior' => 1,
                        ':id_estado_nuevo'    => $idEstadoNuevo,
                        ':id_usuario'         => $usuarioHist,
                        ':observaciones'      => $obsFinal,
                        ':created_at'         => date('Y-m-d H:i:s'),
                    ]);
                }
            } else {
                // Modo 'agregar': si el pedido ya tenía el mismo estado antes del UPDATE, el trigger no insertó fila;
                // aseguramos que quede registrado si se pidió agregar observación.
                if ($estadoAnterior === $idEstadoNuevo) {
                    $stmtInsHist->execute([
                        ':id_pedido'          => $idPedido,
                        ':id_estado_anterior' => $estadoAnterior ?: 1,
                        ':id_estado_nuevo'    => $idEstadoNuevo,
                        ':id_usuario'         => $adminUserId,
                        ':observaciones'      => $obsFinal,
                        ':created_at'         => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            // 3. Registrar en auditoría general
            try {
                AuditoriaModel::registrar(
                    'pedidos',
                    $idPedido,
                    'actualizar',
                    $adminUserId,
                    [
                        'id_estado' => $estadoAnterior,
                        'estado'    => $ped['estado_anterior_nombre'] ?? null,
                    ],
                    [
                        'id_estado'      => $idEstadoNuevo,
                        'estado'         => $nombreEstadoNuevo,
                        'modo_historial' => $modoHistorial,
                        'observaciones'  => $obsFinal,
                    ]
                );
            } catch (Exception $auditEx) {
                error_log('[admin_cambiar_estado] Error auditoría: ' . $auditEx->getMessage());
            }

            $actualizados[] = [
                'id'              => $idPedido,
                'numero_orden'    => $ped['numero_orden'],
                'estado_anterior' => $ped['estado_anterior_nombre'] ?? $estadoAnterior,
                'estado_nuevo'    => $nombreEstadoNuevo,
            ];
        }

        $db->commit();

        // 4. Disparar webhook opcionalmente fuera de la transacción
        if ($dispararWebhook) {
            require_once __DIR__ . '/../modelo/webhook.php';
            foreach ($actualizados as $act) {
                try {
                    WebhookModel::dispararPorPedidoId((int)$act['id'], $idEstadoNuevo, $obsFinal);
                } catch (Exception $wEx) {
                    error_log('[admin_cambiar_estado] Error webhook pedido ' . $act['id'] . ': ' . $wEx->getMessage());
                }
            }
        }

        echo json_encode([
            'success'      => true,
            'message'      => count($actualizados) . ' pedido(s) actualizado(s) a "' . $nombreEstadoNuevo . '" correctamente.',
            'actualizados' => $actualizados,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
