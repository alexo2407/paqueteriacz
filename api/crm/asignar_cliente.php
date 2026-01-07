<?php
/**
 * API Endpoint para asignar cliente a un lead
 * Solo accesible por proveedores
 */

header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modelo/crm_lead.php';
require_once __DIR__ . '/../../modelo/crm_notification.php';
require_once __DIR__ . '/../../utils/crm_roles.php';

$userId = $_SESSION['idUsuario'] ?? 0;

// Validar que sea proveedor
if (!isUserProveedor($userId)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$leadId = $_POST['lead_id'] ?? 0;
$clienteId = $_POST['cliente_id'] ?? 0;

if ($leadId <= 0 || $clienteId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// Verificar que el lead pertenezca al proveedor
$lead = CrmLead::obtenerPorId($leadId);
if (!$lead || (int)$lead['proveedor_id'] !== $userId) {
    echo json_encode(['success' => false, 'message' => 'Lead no encontrado o no autorizado']);
    exit;
}

// Verificar que el lead no tenga ya un cliente asignado
if (!empty($lead['cliente_id'])) {
    echo json_encode(['success' => false, 'message' => 'Este lead ya tiene un cliente asignado']);
    exit;
}

// Actualizar cliente_id
$resultado = CrmLead::asignarCliente($leadId, $clienteId);

if ($resultado['success']) {
    // Crear notificación para el cliente
    CrmNotificationModel::agregar(
        'SEND_TO_CLIENT',
        $leadId,
        $clienteId,
        [
            'lead_id' => $leadId,
            'proveedor_lead_id' => $lead['proveedor_lead_id'],
            'nombre' => $lead['nombre'],
            'telefono' => $lead['telefono'],
            'producto' => $lead['producto'],
            'assigned_at' => date('Y-m-d H:i:s')
        ]
    );
}

echo json_encode($resultado);
