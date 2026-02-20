<?php
/**
 * POST /api/pedidos/bloquear_edicion
 * 
 * Permite al admin bloquear o desbloquear la edición de un pedido
 * 
 * Body: {
 *   "id_pedido": 123,
 *   "bloqueado": true  // true = bloquear, false = desbloquear
 * }
 */
require_once __DIR__ . '/../../modelo/conexion.php';
require_once __DIR__ . '/../utils/responder.php';
require_once __DIR__ . '/../utils/autenticacion.php';

$auth = new AuthMiddleware();
$authResult = $auth->validarToken();

if (!$authResult['success']) {
    responder(false, "No autorizado", null, 401);
    exit;
}

// Verificar que sea Admin
$userRole = $authResult['role'];
$isAdmin = (is_numeric($userRole) && (int)$userRole === 1) || 
           (is_string($userRole) && strcasecmp(trim($userRole), 'Admin') === 0);

if (!$isAdmin) {
    responder(false, "ERROR_PERMISOS", ["detail" => "Solo administradores pueden bloquear/desbloquear pedidos"], 403);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id_pedido']) || !isset($input['bloqueado'])) {
    responder(false, "Datos inválidos. Se requiere id_pedido y bloqueado", null, 400);
    exit;
}

try {
    $db = (new Conexion())->conectar();
    
    $bloqueado = $input['bloqueado'] ? 1 : 0;
    
    $stmt = $db->prepare("
        UPDATE pedidos 
        SET bloqueado_edicion = :bloqueado 
        WHERE id = :id_pedido
    ");
    
    $stmt->execute([
        ':bloqueado' => $bloqueado,
        ':id_pedido' => (int)$input['id_pedido']
    ]);
    
    if ($stmt->rowCount() > 0) {
        $mensaje = $bloqueado 
            ? "Pedido bloqueado. Solo administradores pueden editarlo." 
            : "Pedido desbloqueado. El proveedor puede editarlo según las reglas.";
        
        responder(true, $mensaje, [
            'id_pedido' => (int)$input['id_pedido'],
            'bloqueado_edicion' => $bloqueado
        ], 200);
    } else {
        responder(false, "No se encontró el pedido o no hubo cambios", null, 404);
    }
    
} catch (Exception $e) {
    responder(false, "Error: " . $e->getMessage(), null, 500);
}
