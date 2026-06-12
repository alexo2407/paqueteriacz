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
$numero_orden = trim($_GET['numero_orden'] ?? '');

if (empty($numero_orden)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El parámetro numero_orden es requerido.']);
    exit;
}

// Solo alfanuméricos, guiones y puntos — máx 100 chars
if (!preg_match('/^[a-zA-Z0-9\-\_\.]+$/', $numero_orden) || strlen($numero_orden) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Número de orden inválido.']);
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
    // 1. Buscar el pedido por numero_orden (sin filtro de rol → acceso global)
    $pedidosResult = PedidosModel::obtenerConFiltros(['numero_orden' => $numero_orden]);
    $pedido        = !empty($pedidosResult) ? $pedidosResult[0] : null;

    if (!$pedido) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró ningún pedido con ese número de orden.',
        ]);
        exit;
    }

    // 2. Obtener historial de cambios de estado
    $historialResult = PedidosModel::obtenerHistorialEstadosFiltrado(
        ['numero_orden' => $numero_orden],
        1,
        50
    );
    $historial = $historialResult['data'] ?? [];

    // 3. Formatear timeline (solo datos públicos)
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

    // 4. Estado actual del pedido
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
    $numeroOrden  = $pedido['Numero_Orden']  ?? $pedido['numero_orden']  ?? $numero_orden;

    // 5. Respuesta pública — SIN datos sensibles
    echo json_encode([
        'success'       => true,
        'numero_orden'  => (string) $numeroOrden,
        'estado'        => $estadoActual,
        'descripcion'   => $descripcion,
        'fecha_entrega' => $fechaEntrega,
        'timeline'      => $timeline,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log('[api/pedidos/rastreo] Error: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
