<?php
/**
 * POST /api/forwarding/webhook_c807.php
 *
 * Endpoint Webhook para recibir actualizaciones de estado en tiempo real desde C807 Xpress.
 * C807 envía un POST con la información de la guía, código de evento, coordenadas y prueba de entrega (POD).
 *
 * Flujo:
 *  1. Validación opcional de token de seguridad (x-token o Bearer en headers).
 *  2. Lectura y parseo del payload JSON de C807.
 *  3. Búsqueda del pedido asociado a través de forwarding_guias o external_order_id.
 *  4. Homologación del código de C807 (y código de razón si aplica) con forwarding_status_mapping.
 *  5. Registro del evento técnico en forwarding_webhook_events.
 *  6. Actualización de pedidos (dispara trigger de historial pedidos_historial_estados).
 *  7. Registro en auditoría y disparo de notificaciones a cliente, proveedor y administradores.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, x-token, X-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Use POST.']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../modelo/conexion.php';
    require_once __DIR__ . '/../../modelo/forwarding.php';
    require_once __DIR__ . '/../../modelo/auditoria.php';

    $db = (new Conexion())->conectar();

    // ── 1. Obtener configuración del proveedor C807 ─────────────────────────
    $provStmt = $db->prepare("SELECT * FROM forwarding_providers WHERE slug = 'c807' AND activo = 1 LIMIT 1");
    $provStmt->execute();
    $provC807 = $provStmt->fetch(PDO::FETCH_ASSOC);

    if (!$provC807) {
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Proveedor C807 no configurado o inactivo.']);
        exit;
    }

    $idProvider = (int)$provC807['id'];
    $creds = is_string($provC807['credentials']) ? json_decode($provC807['credentials'], true) : ($provC807['credentials'] ?? []);
    $webhookSecret = trim($creds['webhook_secret'] ?? '');

    // ── 2. Validación de seguridad si webhook_secret está configurado ────────
    if ($webhookSecret !== '') {
        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        $receivedToken = $_SERVER['HTTP_X_TOKEN'] 
            ?? $headers['x-token'] 
            ?? $headers['X-Token'] 
            ?? null;

        if (!$receivedToken) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $headers['Authorization'] ?? '';
            if (preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $m)) {
                $receivedToken = trim($m[1]);
            }
        }

        if (!$receivedToken || !hash_equals($webhookSecret, (string)$receivedToken)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido o no proporcionado.']);
            exit;
        }
    }

    // ── 3. Parsear JSON recibido ────────────────────────────────────────────
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data || !is_array($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cuerpo JSON inválido o vacío.']);
        exit;
    }

    $guiaExterna  = trim($data['guia'] ?? '');
    $codigoEvento = trim((string)($data['codigo'] ?? ''));
    $nombreEstatus= trim($data['estatus'] ?? '');
    $fechaEvento  = trim($data['fecha'] ?? '') ?: date('Y-m-d H:i:s');
    $observaciones= trim($data['observaciones'] ?? '');
    $latitud      = isset($data['latitud']) && is_numeric($data['latitud']) ? (float)$data['latitud'] : null;
    $longitud     = isset($data['longitud']) && is_numeric($data['longitud']) ? (float)$data['longitud'] : null;

    if ($guiaExterna === '' || $codigoEvento === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Campos obligatorios faltantes: guia y codigo.']);
        exit;
    }

    // Razon opcional (para problemas en gestión / no entrega)
    $razonCodigo = null;
    $razonDesc   = null;
    if (!empty($data['razon']) && is_array($data['razon'])) {
        $razonCodigo = isset($data['razon']['codigo']) ? trim((string)$data['razon']['codigo']) : null;
        $razonDesc   = isset($data['razon']['descripcion']) ? trim($data['razon']['descripcion']) : null;
    }

    // Comprobante POD
    $tienePod = !empty($data['pod']) && is_array($data['pod']) ? 1 : 0;
    $podInfo  = null;
    if ($tienePod) {
        $podInfo = json_encode(array_map(function($p) {
            return [
                'nombre' => $p['nombre'] ?? 'COMPROBANTE',
                'tiene_archivo' => !empty($p['valor']),
            ];
        }, $data['pod']), JSON_UNESCAPED_UNICODE);
    }

    // ── 4. Buscar el pedido asociado en forwarding_guias o pedidos ──────────
    $idPedido = null;
    $stmtGuia = $db->prepare("
        SELECT id_pedido, numero_orden 
        FROM forwarding_guias 
        WHERE id_provider = :id_provider AND numero_guia = :guia 
        LIMIT 1
    ");
    $stmtGuia->execute([':id_provider' => $idProvider, ':guia' => $guiaExterna]);
    $rowGuia = $stmtGuia->fetch(PDO::FETCH_ASSOC);

    if ($rowGuia) {
        $idPedido = (int)$rowGuia['id_pedido'];
    } else {
        // Fallback: buscar por external_order_id en forwarding_log
        $stmtLog = $db->prepare("
            SELECT id_pedido 
            FROM forwarding_log 
            WHERE id_provider = :id_provider AND external_order_id = :guia 
            ORDER BY id DESC LIMIT 1
        ");
        $stmtLog->execute([':id_provider' => $idProvider, ':guia' => $guiaExterna]);
        $rowLog = $stmtLog->fetch(PDO::FETCH_ASSOC);
        if ($rowLog) {
            $idPedido = (int)$rowLog['id_pedido'];
        }
    }

    // ── 5. Resolver estado interno mediante forwarding_status_mapping ────────
    $stmtMap = $db->prepare("
        SELECT id_estado_interno, es_confirmado, descripcion_externa 
        FROM forwarding_status_mapping 
        WHERE provider_slug = 'c807' 
          AND codigo_externo = :codigo 
          AND (razon_codigo = :razon OR razon_codigo IS NULL)
          AND activo = 1
        ORDER BY razon_codigo DESC 
        LIMIT 1
    ");
    $stmtMap->execute([':codigo' => $codigoEvento, ':razon' => $razonCodigo]);
    $mapping = $stmtMap->fetch(PDO::FETCH_ASSOC);

    $idEstadoNuevo = $mapping ? (int)$mapping['id_estado_interno'] : null;

    // ── 6. Registrar evento técnico en forwarding_webhook_events ────────────
    $stmtEvent = $db->prepare("
        INSERT INTO forwarding_webhook_events 
        (id_provider, id_pedido, numero_guia, codigo_evento, nombre_estatus, fecha_evento, 
         observaciones, razon_codigo, razon_descripcion, latitud, longitud, tiene_pod, pod_info, payload_raw, procesado, id_estado_interno)
        VALUES 
        (:id_provider, :id_pedido, :numero_guia, :codigo_evento, :nombre_estatus, :fecha_evento, 
         :observaciones, :razon_codigo, :razon_descripcion, :latitud, :longitud, :tiene_pod, :pod_info, :payload_raw, :procesado, :id_estado_interno)
        ON DUPLICATE KEY UPDATE 
            fecha_evento = VALUES(fecha_evento),
            observaciones = VALUES(observaciones),
            procesado = VALUES(procesado),
            id_estado_interno = VALUES(id_estado_interno)
    ");
    $stmtEvent->execute([
        ':id_provider'       => $idProvider,
        ':id_pedido'         => $idPedido,
        ':numero_guia'       => $guiaExterna,
        ':codigo_evento'     => $codigoEvento,
        ':nombre_estatus'    => $nombreEstatus,
        ':fecha_evento'      => $fechaEvento,
        ':observaciones'     => $observaciones,
        ':razon_codigo'      => $razonCodigo,
        ':razon_descripcion' => $razonDesc,
        ':latitud'           => $latitud,
        ':longitud'          => $longitud,
        ':tiene_pod'         => $tienePod,
        ':pod_info'          => $podInfo,
        ':payload_raw'       => $rawInput,
        ':procesado'         => $idEstadoNuevo ? 1 : 0,
        ':id_estado_interno' => $idEstadoNuevo,
    ]);

    // Si no se encontró el pedido, responder OK pero sin actualizar
    if (!$idPedido) {
        echo json_encode([
            'success' => true,
            'message' => "Evento registrado, pero la guía '{$guiaExterna}' no está vinculada a un pedido interno.",
            'processed' => false,
        ]);
        exit;
    }

    // ── 7. Actualizar el pedido en RutaEx si hay homologación ────────────────
    if ($idEstadoNuevo) {
        $stmtPed = $db->prepare("
            SELECT p.id, p.id_estado, p.id_cliente, p.id_proveedor, p.numero_orden, ep.nombre_estado 
            FROM pedidos p 
            LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado 
            WHERE p.id = :id LIMIT 1
        ");
        $stmtPed->execute([':id' => $idPedido]);
        $pedido = $stmtPed->fetch(PDO::FETCH_ASSOC);

        if ($pedido) {
            $estadoAntId     = (int)$pedido['id_estado'];
            $estadoAntNombre = $pedido['nombre_estado'] ?? '';

            // Obtener nombre del nuevo estado
            $stmtNom = $db->prepare("SELECT nombre_estado FROM estados_pedidos WHERE id = :id");
            $stmtNom->execute([':id' => $idEstadoNuevo]);
            $estadoNuevoNombre = $stmtNom->fetchColumn() ?: "Estado #{$idEstadoNuevo}";

            // Construir texto de observaciones para el historial
            $obsTexto = "C807 [{$codigoEvento}] {$nombreEstatus}";
            if ($razonDesc) {
                $obsTexto .= " - Motivo: {$razonDesc}";
            }
            if ($observaciones !== '') {
                $obsTexto .= " ({$observaciones})";
            }

            // Inyectar variables de sesión MySQL para el trigger after_pedido_update_estado
            $db->exec("SET @current_user_id = NULL, @current_observaciones = " . $db->quote($obsTexto) . ", @current_created_at = " . $db->quote($fechaEvento));

            // Actualizar pedido
            $upd = $db->prepare("UPDATE pedidos SET id_estado = :estado, updated_at = NOW() WHERE id = :id");
            $upd->execute([':estado' => $idEstadoNuevo, ':id' => $idPedido]);

            // Auditoría
            AuditoriaModel::registrar(
                'pedidos',
                $idPedido,
                'actualizar',
                null,
                ['id_estado' => $estadoAntId, 'estado' => $estadoAntNombre],
                ['id_estado' => $idEstadoNuevo, 'estado' => $estadoNuevoNombre, 'observaciones' => $obsTexto]
            );

            // Notificación logística interna (campanita web para cliente, proveedor y admin)
            try {
                require_once __DIR__ . '/../../modelo/logistica_notification.php';
                $destinatarios = [];
                if (!empty($pedido['id_cliente']))   $destinatarios[(int)$pedido['id_cliente']] = true;
                if (!empty($pedido['id_proveedor'])) $destinatarios[(int)$pedido['id_proveedor']] = true;

                $adminStmt = $db->query("
                    SELECT DISTINCT u.id 
                    FROM usuarios u 
                    INNER JOIN usuarios_roles ur ON ur.id_usuario = u.id 
                    INNER JOIN roles r ON r.id = ur.id_rol 
                    WHERE r.nombre = 'Administrador'
                ");
                foreach ($adminStmt->fetchAll(PDO::FETCH_COLUMN) as $admId) {
                    $destinatarios[(int)$admId] = true;
                }

                $tituloNotif  = "Pedido #{$pedido['numero_orden']} → {$estadoNuevoNombre}";
                $payloadNotif = [
                    'estado_anterior' => $estadoAntNombre,
                    'estado_nuevo'    => $estadoNuevoNombre,
                    'numero_orden'    => $pedido['numero_orden'],
                    'guia'            => $guiaExterna,
                    'proveedor'       => 'C807 Xpress',
                ];

                foreach (array_keys($destinatarios) as $uid) {
                    LogisticaNotificationModel::agregar($uid, 'estado_cambiado', $tituloNotif, $obsTexto, $idPedido, $payloadNotif);
                }
            } catch (Throwable $eNotif) {
                error_log("Error al crear notificación C807: " . $eNotif->getMessage());
            }

            // Disparar webhooks salientes (WooCommerce, Shopify u otros consumidores)
            try {
                require_once __DIR__ . '/../../modelo/webhook.php';
                WebhookModel::dispararPorPedidoId($idPedido, $idEstadoNuevo, $obsTexto);
            } catch (Throwable $eWh) {
                error_log("Error al disparar webhook saliente C807: " . $eWh->getMessage());
            }
        }
    }

    echo json_encode([
        'success'           => true,
        'message'           => 'Evento de C807 procesado exitosamente.',
        'guia'              => $guiaExterna,
        'id_pedido'         => $idPedido,
        'id_estado_interno' => $idEstadoNuevo,
    ]);

} catch (Throwable $e) {
    error_log("webhook_c807.php fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
