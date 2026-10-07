<?php

/**
 * GET /api/pedidos/rastreo?numero_orden=XXXXX
 *
 * Endpoint PÚBLICO de rastreo — NO requiere autenticación JWT.
 * Expone únicamente datos seguros para el destinatario final:
 *   - Número de orden
 *   - Estado actual
 *   - Historial de cambios de estado (fecha, estado, operador)
 *
 * Seguridad:
 *   - Rate limiting por IP: 30 peticiones / minuto
 *   - Solo campos públicos (sin datos de cliente, precios, direcciones)
 *   - CORS restringido a dominios autorizados
 *
 * Uso:
 *   GET /api/pedidos/rastreo?numero_orden=9980944879
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ── CORS: solo desde dominios autorizados ─────────────────────────────────────
$allowedOrigins = [
    'https://rutaex.com',
    'https://www.rutaex.com',
    'http://localhost',
    'http://localhost:3000',
    'http://127.0.0.1',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} else {
    // Origen no registrado: CORS bloqueado (pero la petición server-to-server sí pasa)
    header('Access-Control-Allow-Origin: https://rutaex.com');
}

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// ── Rate limiting por IP ───────────────────────────────────────────────────────
$ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rl_dir    = sys_get_temp_dir();
$rl_file   = $rl_dir . '/pcz_rastreo_rl_' . md5($ip) . '.json';
$rl_limit  = 30;   // peticiones máximas
$rl_window = 60;   // en segundos
$now       = time();

$rl = ['count' => 0, 'window_start' => $now];
if (file_exists($rl_file)) {
    $rl = json_decode(file_get_contents($rl_file), true) ?: $rl;
}

if (($now - $rl['window_start']) > $rl_window) {
    $rl = ['count' => 1, 'window_start' => $now];
} else {
    $rl['count']++;
    if ($rl['count'] > $rl_limit) {
        http_response_code(429);
        header('Retry-After: ' . ($rl_window - ($now - $rl['window_start'])));
        echo json_encode([
            'success' => false,
            'message' => 'Demasiadas solicitudes. Intenta de nuevo en un momento.',
        ]);
        exit;
    }
}
@file_put_contents($rl_file, json_encode($rl));

// ── Validar parámetro ─────────────────────────────────────────────────────────
$codigo   = trim($_GET['numero_orden'] ?? $_GET['numero_traking'] ?? $_GET['tracking'] ?? $_GET['guia'] ?? '');
$telefono = trim($_GET['telefono'] ?? $_GET['phone'] ?? '');

if (empty($codigo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El parámetro numero_orden o numero_traking es requerido.']);
    exit;
}

// Solo alfanuméricos, guiones y puntos — máx 100 chars
if (!preg_match('/^[a-zA-Z0-9\-\_\.]+$/', $codigo) || strlen($codigo) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Número de orden o código de tracking inválido.']);
    exit;
}

// ── Carga de dependencias ─────────────────────────────────────────────────────
try {
    require_once __DIR__ . '/../../modelo/pedido.php';
    require_once __DIR__ . '/../utils/responder.php';
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Servicio no disponible.']);
    exit;
}

// ── Consultar pedido y su historial ──────────────────────────────────────────
try {
    $db = (new Conexion())->conectar();

    // 1. Buscar primero por número de tracking (identificador logístico único)
    $stmtTrk = $db->prepare("
        SELECT 
            p.id AS ID_Pedido,
            p.id,
            p.numero_orden AS Numero_Orden,
            p.numero_orden,
            p.numero_traking,
            p.destinatario AS Cliente,
            p.telefono AS Telefono,
            p.telefono,
            p.fecha_entrega,
            p.comentario,
            ep.nombre_estado AS Estado,
            ep.nombre_estado
        FROM pedidos p
        LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
        WHERE p.numero_traking = :trk
        LIMIT 1
    ");
    $stmtTrk->execute([':trk' => $codigo]);
    $trkPedido = $stmtTrk->fetch(PDO::FETCH_ASSOC);

    if ($trkPedido) {
        $pedido = $trkPedido;
    } else {
        // 2. Si no es tracking, buscar por numero_orden
        $pedidosResult = PedidosModel::obtenerConFiltros(['numero_orden' => $codigo]);

        if (empty($pedidosResult)) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'No se encontró ningún pedido con ese número de orden o tracking.',
            ]);
            exit;
        }

        // Si existen múltiples pedidos con el mismo número de orden (distintos clientes):
        if (count($pedidosResult) > 1) {
            if (!empty($telefono)) {
                $cleanTel = preg_replace('/\D/', '', $telefono);
                $filtrados = array_filter($pedidosResult, function($p) use ($cleanTel) {
                    $pTel = preg_replace('/\D/', '', $p['Telefono'] ?? $p['telefono'] ?? '');
                    if (empty($pTel) || empty($cleanTel)) return false;
                    return (str_ends_with($pTel, $cleanTel) || str_ends_with($cleanTel, $pTel));
                });
                if (!empty($filtrados)) {
                    $pedido = reset($filtrados);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'El teléfono ingresado no coincide con el número de orden.',
                    ]);
                    exit;
                }
            } else {
                // Protección de datos: requerir teléfono para desambiguar
                http_response_code(422);
                echo json_encode([
                    'success'           => false,
                    'requiere_telefono' => true,
                    'message'           => 'Existen múltiples pedidos asociados a este número. Por tu seguridad, ingresa el número de teléfono registrado para consultar tu entrega.',
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            $pedido = $pedidosResult[0];
        }
    }

    // 3. Obtener historial de cambios de estado específico del pedido encontrado
    $idPedido = (int)($pedido['ID_Pedido'] ?? $pedido['id'] ?? 0);
    $historialResult = PedidosModel::obtenerHistorialEstadosFiltrado(
        ['id_pedido' => $idPedido],
        1,
        50
    );
    $historial = $historialResult['data'] ?? [];

    // 4. Formatear timeline (solo datos públicos)
    $timeline = [];
    $tz = new DateTimeZone('America/Managua');
    foreach ($historial as $cambio) {
        $fecha = '';
        if (!empty($cambio['fecha_cambio'])) {
            try {
                $dt    = new DateTime($cambio['fecha_cambio'], new DateTimeZone(date_default_timezone_get()));
                $dt->setTimezone($tz);
                $fecha = $dt->format('d M Y - H:i');
            } catch (Exception $e) {
                $fecha = $cambio['fecha_cambio'];
            }
        }
        $timeline[] = [
            'estado'        => $cambio['estado_nuevo']  ?? '',
            'fecha'         => $fecha,
            'operador'      => $cambio['realizado_por'] ?? 'RutaEx Latam',
        ];
    }
    // El más reciente primero
    $timeline = array_reverse($timeline);

    // 5. Estado actual del pedido
    $estadoActual = $pedido['Estado'] ?? $pedido['nombre_estado'] ?? 'Desconocido';

    // Descripción pública por estado
    $descripciones = [
        'En bodega'                         => 'Tu paquete está en nuestras instalaciones.',
        'En ruta o proceso'                 => 'Tu paquete está en camino hacia su destino.',
        'Entregado'                         => 'Tu paquete fue entregado exitosamente.',
        'Entregado-liquidado'               => 'Tu paquete fue entregado exitosamente.',
        'Reprogramado'                      => 'La entrega fue reprogramada. Te contactaremos pronto.',
        'Domicilio cerrado'                 => 'El domicilio estaba cerrado. Reintentaremos la entrega.',
        'No hay quien reciba en domicilio'  => 'No había nadie en el domicilio. Coordinaremos una nueva entrega.',
        'Devuelto'                          => 'El paquete está siendo devuelto al remitente.',
        'Devuelto a bodega'                 => 'El paquete ha regresado a nuestras instalaciones.',
        'Domicilio no encontrado'           => 'No fue posible ubicar el domicilio. Verifica tu dirección.',
        'Rechazado'                         => 'El destinatario rechazó el paquete.',
        'No puede pagar recaudo'            => 'El destinatario no pudo realizar el pago contra entrega.',
        'Pendiente recolección'             => 'Pendiente de ser recolectado por el mensajero.',
        'Recolectado por mensajería'        => 'El paquete fue recolectado y está en proceso de envío.',
        'Traslado a punto de distribución'  => 'Trasladando al centro de distribución.',
        'Incidencia'                        => 'Existe una incidencia. Nuestro equipo te contactará.',
        'Cancelado'                         => 'El pedido fue cancelado.',
    ];

    $descripcion  = $descripciones[$estadoActual] ?? 'Estado de tu envío actualizado.';
    $fechaEntrega = $pedido['Fecha_Entrega'] ?? $pedido['fecha_entrega'] ?? null;
    $numeroOrden  = $pedido['Numero_Orden']  ?? $pedido['numero_orden']  ?? $codigo;

    // 6. Respuesta pública — SIN datos sensibles
    echo json_encode([
        'success'        => true,
        'numero_orden'   => (string) $numeroOrden,
        'numero_traking' => $pedido['numero_traking'] ?? null,
        'estado'         => $estadoActual,
        'descripcion'    => $descripcion,
        'fecha_entrega'  => $fechaEntrega,
        'timeline'       => $timeline,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    error_log('[api/pedidos/rastreo] Error: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
