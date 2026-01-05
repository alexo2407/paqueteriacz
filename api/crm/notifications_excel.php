<?php
// api/crm/notifications_excel.php
// Eliminado auth_api.php
require_once __DIR__ . '/../../modelo/crm_notification.php';
require_once __DIR__ . '/../../modelo/conexion.php';
require_once __DIR__ . '/../../modelo/conexion.php';

if (session_status() == PHP_SESSION_NONE) session_start();
$userId = $_SESSION['idUsuario'] ?? 0;

if ($userId <= 0) { die("Acceso denegado"); }

// Filtros
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$leadStatus = $_GET['lead_status'] ?? null;
// Si user quiere todo absoluto, podría enviar fechas vacias.
// Pero por seguridad/performance, si no envía fechas, ponemos un default razonable (ej. año actual) o todo.
// Dejemos que si están vacíos traiga todo (o limitado a 5000).

// Aumentar límite memoria y tiempo para exportación grande
ini_set('memory_limit', '512M');
set_time_limit(300);

// Obtener datos (Sin paginación = límites altos)
// Usamos offset 0 y un limite muy alto (ej 10000)
$notificaciones = CrmNotificationModel::obtenerPorUsuario($userId, false, 10000, 0, '', $startDate, $endDate, $leadStatus);

// Headers para descarga CSV
$filename = "historial_notificaciones_" . date('Y-m-d_H-i') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Abrir stream salida
$output = fopen('php://output', 'w');

// BOM para UTF-8 Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados Columnas
fputcsv($output, ['ID', 'Fecha', 'Tipo', 'Lead', 'Telefono', 'Estado', 'Detalle']);

foreach ($notificaciones as $notif) {
    $payload = is_array($notif['payload']) ? $notif['payload'] : json_decode($notif['payload'], true);
    
    // Preparar filas
    $fecha = $notif['created_at'];
    $tipoRaw = $notif['type'];
    $tipo = ($tipoRaw == 'new_lead') ? 'Nuevo Lead' : 'Actualización';
    
    $nombreLead = $payload['nombre'] ?? ($notif['lead_name_live'] ?? 'Desc.');
    $telefono = $payload['telefono'] ?? ($notif['lead_phone_live'] ?? '');
    $estado = $notif['lead_status_live'] ?? '';
    
    $detalle = '';
    if ($tipoRaw == 'new_lead') {
        $detalle = "Interesado en: " . ($payload['producto'] ?? '-');
    } else {
        $ant = $payload['estado_anterior'] ?? '';
        $nue = $payload['estado_nuevo'] ?? '';
        $detalle = "Cambio de $ant a $nue";
    }
    
    fputcsv($output, [
        $notif['id'],
        $fecha,
        $tipo,
        $nombreLead,
        $telefono,
        $estado,
        $detalle
    ]);
}

fclose($output);
exit;
