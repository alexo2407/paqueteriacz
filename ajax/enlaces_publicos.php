<?php
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/permissions.php';
require_once __DIR__ . '/../modelo/conexion.php';

start_secure_session();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['registrado'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método inválido']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = getCurrentUserId();

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'ID de usuario no encontrado']);
    exit;
}

try {
    $db = (new Conexion())->conectar();

    if ($action === 'habilitar') {
        // Generar un token único seguro de 64 caracteres
        $newToken = bin2hex(random_bytes(32));
        
        $stmt = $db->prepare("UPDATE usuarios SET token_enlace_publico = :token WHERE id = :id");
        $stmt->execute([':token' => $newToken, ':id' => $userId]);
        
        echo json_encode(['success' => true, 'token' => $newToken, 'message' => 'Enlace público habilitado']);
        
    } elseif ($action === 'deshabilitar') {
        $stmt = $db->prepare("UPDATE usuarios SET token_enlace_publico = NULL WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Enlace público revocado. Los enlaces antiguos ya no funcionarán.']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción desconocida']);
    }

} catch (Exception $e) {
    error_log('Error en enlaces_publicos.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de servidor: ' . $e->getMessage()]);
}
