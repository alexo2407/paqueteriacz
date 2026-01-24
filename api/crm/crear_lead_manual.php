<?php
/**
 * API Endpoint para crear un lead manualmente por el proveedor
 */

header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modelo/crm_lead.php';
require_once __DIR__ . '/../../modelo/crm_notification.php';
require_once __DIR__ . '/../../utils/crm_roles.php';

$userId = $_SESSION['idUsuario'] ?? 0;

// Validar que sea proveedor
if (!isProveedorCRM($userId)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// Recibir datos JSON o POST
$jsonData = json_decode(file_get_contents('php://input'), true);
$nombre = $jsonData['nombre'] ?? $_POST['nombre'] ?? '';
$telefono = $jsonData['telefono'] ?? $_POST['telefono'] ?? '';
$producto = $jsonData['producto'] ?? $_POST['producto'] ?? '';
$clienteId = $jsonData['cliente_id'] ?? $_POST['cliente_id'] ?? null;

// Validaciones básicas
if (empty($nombre) || empty($producto)) {
    echo json_encode(['success' => false, 'message' => 'Nombre y Producto son obligatorios']);
    exit;
}

// Generar ID único para el proveedor_lead_id
// Formato: MAN-{PROV_ID}-{TIMESTAMP}
$proveedorLeadId = 'MAN-' . $userId . '-' . time();

// Preparar datos del lead
$leadData = [
    'proveedor_id' => $userId,
    'proveedor_lead_id' => $proveedorLeadId,
    'cliente_id' => !empty($clienteId) ? $clienteId : null,
    'nombre' => $nombre,
    'telefono' => $telefono,
    'producto' => $producto,
    'estado_actual' => 'EN_ESPERA', // Estado por defecto
    'fecha_hora' => date('Y-m-d H:i:s')
];

// Crear el lead
$resultado = CrmLeadModel::crearLead($leadData, $userId);
$nuevoLeadId = $resultado['lead_id'] ?? 0;

if ($resultado['success'] && $nuevoLeadId) {
    // Si se asignó un cliente, crear notificación para él
    if (!empty($clienteId)) {
        CrmNotificationModel::agregar(
            'SEND_TO_CLIENT',
            $nuevoLeadId,
            $clienteId,
            [
                'lead_id' => $nuevoLeadId,
                'proveedor_lead_id' => $proveedorLeadId,
                'nombre' => $nombre,
                'telefono' => $telefono,
                'producto' => $producto,
                'assigned_at' => date('Y-m-d H:i:s')
            ]
        );
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Lead creado correctamente',
        'lead_id' => $nuevoLeadId
    ]);
} else {
    $mensajeError = $resultado['message'] ?? 'Error al crear el lead';
    echo json_encode(['success' => false, 'message' => $mensajeError]);
}
