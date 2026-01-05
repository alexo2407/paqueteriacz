<?php
// api/crm/notifications_datatable.php
// Silenciar output de errores HTML que rompen el JSON
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modelo/crm_notification.php';
require_once __DIR__ . '/../../modelo/conexion.php';

// Simulación de sesión si no usas JWT puro aquí, o asegurar que session_start esté
if (session_status() == PHP_SESSION_NONE) session_start();
$userId = $_SESSION['idUsuario'] ?? 0;

if ($userId <= 0) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// 1. Parámetros DataTables
$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$searchValue = $_GET['search']['value'] ?? '';

// 2. Parámetros Custom (Filtros Fecha)
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$leadStatus = $_GET['lead_status'] ?? null;
$tab = $_GET['tab'] ?? 'all';

// Validar fechas vacías
if (empty($startDate) || $startDate == 'null') $startDate = null;
if (empty($endDate) || $endDate == 'null') $endDate = null;
if (empty($leadStatus) || $leadStatus == 'null') $leadStatus = null;

// 3. Consultar Datos
$notificaciones = CrmNotificationModel::obtenerPorUsuario($userId, false, $length, $start, $searchValue, $startDate, $endDate, $leadStatus);
$totalRecords = CrmNotificationModel::contarTotalPorUsuario($userId, false, '', $startDate, $endDate, $leadStatus);
$filteredRecords = CrmNotificationModel::contarTotalPorUsuario($userId, false, $searchValue, $startDate, $endDate, $leadStatus);

// 4. Construir Array DataTables
$data = [];

foreach ($notificaciones as $notif) {
    // Renderizar tarjeta a HTML string
    $html = renderCardHtml($notif);
    
    // Datos auxiliares para columnas ocultas
    $payload = is_array($notif['payload']) ? $notif['payload'] : json_decode($notif['payload'], true);
    $rawDate = $notif['created_at'];
    $searchText = strtolower(($payload['nombre'] ?? '') . ' ' . ($payload['telefono'] ?? '') . ' ' . ($notif['lead_status_live'] ?? ''));

    $data[] = [
        $html,       // Columna 0: Visible (HTML Card)
        $rawDate,    // Columna 1: Oculta (Sort)
        $searchText  // Columna 2: Oculta (Search)
    ];
}

// 5. Respuesta JSON DataTables
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($filteredRecords),
    "data" => $data
]);


//HELPER: Renderizado de Tarjeta (Duplicado de la vista para servir via AJX)
function renderCardHtml($notif) {
    $payload = is_array($notif['payload']) ? $notif['payload'] : json_decode($notif['payload'], true);
    
    $isRead = $notif['is_read'];
    $unreadClass = $isRead ? '' : 'unread shadow-sm';
    $time = date('d/m H:i', strtotime($notif['created_at'])); // Incluir fecha corta
    
    $leadStatusLive = $notif['lead_status_live'] ?? null;
    
    $colores = [
        'EN_ESPERA' => 'bg-warning text-dark',
        'nuevo'     => 'bg-warning text-dark',
        'APROBADO'  => 'bg-success',
        'CONFIRMADO'=> 'bg-primary',
        'EN_TRANSITO'=> 'bg-info text-dark',
        'EN_BODEGA' => 'bg-secondary',
        'CANCELADO' => 'bg-danger'
    ];
    
    $badgeClass = $colores[$leadStatusLive] ?? 'bg-light text-dark border';
    $estadoBadge = $leadStatusLive ? "<span class='badge {$badgeClass} me-1'>{$leadStatusLive}</span>" : "";
    
    $rutaUrl = '/paqueteriacz/'; // Ajustar si es necesario obtener dinámicamente

    if ($notif['type'] === 'new_lead') {
        $icon = '<i class="bi bi-person-plus-fill"></i>';
        $title = $payload['nombre'] ?? 'Nuevo Lead';
        $leadId = $payload['lead_id'] ?? 0;
        $iconClass = 'bg-soft-success';
        $typeClass = 'type-lead';
        $prodInfo = isset($payload['producto']) ? "Interesado en: <b>{$payload['producto']}</b>" : 'Nuevo cliente';
        $subtitle = $estadoBadge . " " . $prodInfo;
    } else {
        $icon = '<i class="bi bi-arrow-repeat"></i>';
        $title = "Actualización Lead #{$notif['related_lead_id']}";
        $leadId = $notif['related_lead_id'] ?? 0;
        $iconClass = 'bg-soft-info';
        $typeClass = 'type-update';
        $estadoAnterior = $payload['estado_anterior'] ?? '?';
        $estadoNuevo = $payload['estado_nuevo'] ?? '?';
        $subtitle = "Cambio: <span class='badge bg-secondary'>$estadoAnterior</span> <i class='bi bi-arrow-right small'></i> <span class='badge bg-primary'>$estadoNuevo</span>";
    }

    // HTML de tarjeta (Card) - Debe coincidir con diseño de notificaciones.php
    return "
    <div class='col-12'> <!-- Asegurar col-12 aquí dentro también -->
        <div class='card notif-card {$unreadClass} {$typeClass} p-3 h-100' id='notif-card-{$notif['id']}'>
            <div class='d-flex align-items-start'>
                <div class='notif-icon {$iconClass} flex-shrink-0 me-3'>
                    {$icon}
                </div>
                <div class='flex-grow-1'>
                    <div class='d-flex justify-content-between align-items-start'>
                        <h6 class='mb-1 fw-bold text-dark'>{$title}</h6>
                        <small class='text-muted ms-2'>{$time}</small>
                    </div>
                    <p class='mb-2 text-muted small'>{$subtitle}</p>
                    <a href='{$rutaUrl}crm/ver/{$leadId}' class='btn btn-sm btn-light border'>Ver Detalles</a>
                </div>
            </div>
        </div>
    </div>";
}
