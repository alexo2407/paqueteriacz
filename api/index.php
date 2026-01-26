<?php
/**
 * Lightweight API router
 *
 * This file provides a minimal router for the local API under /api/.
 * It maps specific request paths and methods to the corresponding
 * PHP endpoint scripts located in the `api/` subfolders.
 *
 * Behavior summary:
 *  - Normalizes the request path and checks explicit routes (auth, pedidos).
 *  - If the path starts with /api/ and no route matches, returns a JSON 404
 *    describing the requested path (helpful for API clients).
 *  - Non-API requests are redirected to the site's 404 page.
 *
 * Notes for maintainers:
 *  - Keep this file minimal. For more routes consider using a micro-router
 *    or framework. Routes are matched using regex against the normalized path.
 */

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);


require_once __DIR__ . '/../config/config.php'; // Configuraciones globales
require_once __DIR__ . '/../vendor/autoload.php'; // Autoload para dependencias

header('Content-Type: application/json');

// Global Exception Handler for API
set_exception_handler(function ($e) {
    $code = $e->getCode();
    $message = $e->getMessage();
    $httpCode = 500;
    $errorCode = 'SERVER_ERROR';

    // Check for integrity constraint violation (SQLSTATE 23000)
    if ($e instanceof PDOException && $e->getCode() == '23000') {
        $httpCode = 400;
        $message = "Datos inválidos: revisa las relaciones enviadas.";
        $errorCode = 'INTEGRITY_CONSTRAINT';
    } elseif ($e->getCode() >= 400 && $e->getCode() < 600) {
        // Allow custom exceptions to set HTTP code
        $httpCode = $e->getCode();
        // If it's a custom exception, we might want to use its message directly
        // assuming it's safe. For now, we trust the message if it's not a generic Error.
    }

    // Hide internal details in production (optional, but requested by user)
    // For now, we follow the rule: "NEVER EXPONER ERRORES SQL NI STACK TRACE"
    if ($e instanceof PDOException && $errorCode === 'SERVER_ERROR') {
         $message = "Error interno del servidor.";
    }

    // ...
    http_response_code($httpCode);
    echo json_encode([
        'error' => $message,
        'code' => $errorCode
    ]);
    exit;
});

// Normalizar la ruta solicitada (sin query string) y quitar slash final
$rawPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($rawPath, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Rutas explícitas mínimas (usar includes relativos correctos)
if (preg_match('/\/api\/auth\/login$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/auth/login.php';
    exit;
}

if (preg_match('/\/api\/pedidos\/listar$/', $path) && $method === 'GET') {
    require_once __DIR__ . '/pedidos/listar.php';
    exit;
}

if (preg_match('/\/api\/pedidos\/crear$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/pedidos/crear.php';
    exit;
}
if (preg_match('/\/api\/pedidos\/multiple$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/pedidos/multiple.php';
    exit;
}
if (preg_match('/\/api\/pedidos\/status$/', $path) && $method === 'GET') {
    require_once __DIR__ . '/pedidos/status.php';
    exit;
}

// Rutas de Geoinfo
if (preg_match('/\/api\/geoinfo\/paises$/', $path)) {
    include __DIR__ . '/geoinfo/paises.php';
    exit;
}
if (preg_match('/\/api\/geoinfo\/departamentos$/', $path)) {
    include __DIR__ . '/geoinfo/departamentos.php';
    exit;
}
if (preg_match('/\/api\/geoinfo\/municipios$/', $path)) {
    include __DIR__ . '/geoinfo/municipios.php';
    exit;
}
if (preg_match('/\/api\/geoinfo\/barrios$/', $path)) {
    include __DIR__ . '/geoinfo/barrios.php';
    exit;
}
if (preg_match('/\/api\/geoinfo\/listar$/', $path)) {
    include __DIR__ . '/geoinfo/listar.php';
    exit;
}

// Rutas de Monedas
if (preg_match('/\/api\/monedas\/listar$/', $path) && $method === 'GET') {
    require_once __DIR__ . '/monedas/listar.php';
    exit;
}
if (preg_match('/\/api\/monedas\/ver$/', $path) && $method === 'GET') {
    require_once __DIR__ . '/monedas/ver.php';
    exit;
}
if (preg_match('/\/api\/monedas\/crear$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/monedas/crear.php';
    exit;
}
if (preg_match('/\/api\/monedas\/actualizar$/', $path) && ($method === 'POST' || $method === 'PUT')) {
    require_once __DIR__ . '/monedas/actualizar.php';
    exit;
}
if (preg_match('/\/api\/monedas\/eliminar$/', $path) && $method === 'DELETE') {
    require_once __DIR__ . '/monedas/eliminar.php';
    exit;
}

// -----------------------
// Rutas CRM Relay
// -----------------------

// POST /api/crm/leads - Recibir leads (individual o batch)
if (preg_match('/\/api\/crm\/leads$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/crm/leads.php';
    exit;
}

// GET /api/crm/leads - Listar leads
if (preg_match('/\/api\/crm\/leads$/', $path) && $method === 'GET') {
    require_once __DIR__ . '/crm/leads_list.php';
    exit;
}

// POST /api/crm/leads/{id}/estado - Actualizar estado
if (preg_match('/\/api\/crm\/leads\/(\d+)\/estado$/', $path, $matches) && $method === 'POST') {
    $_GET['lead_id'] = $matches[1];
    require_once __DIR__ . '/crm/lead_status.php';
    exit;
}

// POST /api/crm/leads/bulk-status - Actualizar estado masivo (síncrono, límite 100)
if (preg_match('/\/api\/crm\/leads\/bulk-status$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/crm/lead_bulk_status.php';
    exit;
}

// POST /api/crm/leads/bulk-status-async - Actualizar estado masivo (asíncrono, sin límite)
if (preg_match('/\/api\/crm\/leads\/bulk-status-async$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/crm/lead_bulk_status_async.php';
    exit;
}

// POST /api/crm/leads/assign-client - Asignar cliente a lead(s)
if (preg_match('/\/api\/crm\/leads\/assign-client$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/crm/lead_assign_client.php';
    exit;
}

// GET /api/crm/jobs/{job_id} - Consultar estado de job asíncrono
if (preg_match('/\/api\/crm\/jobs\/([a-zA-Z0-9_]+)$/', $path, $matches) && $method === 'GET') {
    $_GET['job_id'] = $matches[1];
    require_once __DIR__ . '/crm/job_status.php';
    exit;
}


// GET /api/crm/leads/{id}/timeline - Ver timeline
if (preg_match('/\/api\/crm\/leads\/(\d+)\/timeline$/', $path, $matches) && $method === 'GET') {
    $_GET['lead_id'] = $matches[1];
    $_GET['action'] = 'timeline';
    require_once __DIR__ . '/crm/lead_detail.php';
    exit;
}

// GET /api/crm/leads/{id} - Ver detalle
if (preg_match('/\/api\/crm\/leads\/(\d+)$/', $path, $matches) && $method === 'GET') {
    $_GET['lead_id'] = $matches[1];
    require_once __DIR__ . '/crm/lead_detail.php';
    exit;
}

// GET /api/crm/notifications - Obtener notificaciones del usuario
if (preg_match('/\/api\/crm\/notifications$/', $path) && $method === 'GET') {
    require_once __DIR__ . '/crm/notifications.php';
    exit;
}

// POST /api/crm/notifications/{id}/read - Marcar notificación como leída
if (preg_match('/\/api\/crm\/notifications\/(\d+)\/read$/', $path, $matches) && $method === 'POST') {
    $_GET['notification_id'] = $matches[1];
    require_once __DIR__ . '/crm/notification_read.php';
    exit;
}

// POST /api/crm/notifications/read-all - Marcar todas como leídas
if (preg_match('/\/api\/crm\/notifications\/read-all$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/crm/notifications_read_all.php';
    exit;
}

// GET /api/crm/metrics - Métricas (admin only)
if (preg_match('/\/api\/crm\/metrics$/', $path) && $method === 'GET') {
    require_once __DIR__ . '/crm/metrics.php';
    exit;
}

// GET /api/crm/provider-metrics - Métricas de proveedor
if (preg_match('/\/api\/crm\/provider-metrics$/', $path) && $method === 'GET') {
    require_once __DIR__ . '/crm/provider_metrics.php';
    exit;
}

// POST/GET /api/crm/notifications_datatable.php - DataTable de notificaciones
if (preg_match('/\/api\/crm\/notifications_datatable\.php$/', $path) && ($method === 'POST' || $method === 'GET')) {
    require_once __DIR__ . '/crm/notifications_datatable.php';
    exit;
}

// POST/GET /api/crm/updates_datatable.php - DataTable de actualizaciones
if (preg_match('/\/api\/crm\/updates_datatable\.php$/', $path) && ($method === 'POST' || $method === 'GET')) {
    require_once __DIR__ . '/crm/updates_datatable.php';
    exit;
}

// -----------------------
// Rutas Cliente (App/Web)
// -----------------------

// GET /api/cliente/pedidos - Listar pedidos del cliente
if (preg_match('/\/api\/cliente\/pedidos$/', $path) && $method === 'GET') {
    require_once __DIR__ . '/cliente/pedidos.php';
    exit;
}

// POST /api/cliente/cambiar_estado - Actualizar estado
if (preg_match('/\/api\/cliente\/cambiar_estado$/', $path) && $method === 'POST') {
    require_once __DIR__ . '/cliente/cambiar_estado.php';
    exit;
}


// Si la ruta está bajo /api/ devolvemos JSON 404 para APIs
if (strpos($path, '/api/') === 0) {
    // Inteligencia Adicional: Si no hubo match explícito arriba, intentar buscar el archivo físicamente
    // Esto previene errores de 404 si el archivo existe pero olvidamos registrarlo en el router.
    $relativePart = substr($path, 5); // Quitar '/api/'
    $possibleFile = __DIR__ . '/' . $relativePart . '.php';
    
    if (file_exists($possibleFile) && is_file($possibleFile)) {
        require_once $possibleFile;
        exit;
    }

    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 404,
            'message' => 'El endpoint solicitado no existe o no está registrado en el router.',
            'path' => $path,
            'timestamp' => date('c')
        ]
    ]);
    exit;
}

// No es una ruta API: redirigir a la página 404 del sitio
http_response_code(404);
header("Location: 404/");
exit;
