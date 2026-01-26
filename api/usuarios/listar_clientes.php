<?php
/**
 * API Endpoint para listar clientes (para Select2)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modelo/usuario.php';
require_once __DIR__ . '/../../utils/crm_roles.php';
// 1. Intentar obtener usuario desde Token (Prioridad API)
require_once __DIR__ . '/../../api/utils/autenticacion.php';
$token = AuthMiddleware::obtenerTokenDeHeaders();
$authUserId = 0;

if ($token) {
    $auth = new AuthMiddleware();
    $check = $auth->validarToken($token);
    if ($check['success']) {
        $authUserId = (int)$check['data']['id'];
    }
}

// 2. Intentar obtener desde Sesión (Fallback Web/Ajax)
if ($authUserId <= 0) {
    require_once __DIR__ . '/../../utils/session.php';
    start_secure_session();
    $authUserId = (int)($_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0);
}

// Bloquear si no hay usuario autenticado por ningún medio
if ($authUserId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado: Token o sesión requerida']);
    exit;
}

// Obtener término de búsqueda
$search = $_GET['q'] ?? '';

try {
    $usuarioModel = new UsuarioModel();
    
    if ($debug) {
        // Listar todos los roles disponibles
        $rolesDisponibles = $usuarioModel->listarRoles();
        
        // Intentar obtener usuarios para varios nombres de rol posibles
        $intentos = [
            'Cliente' => $usuarioModel->obtenerUsuariosPorRolNombre('Cliente'),
            'CLIENTE' => $usuarioModel->obtenerUsuariosPorRolNombre('CLIENTE'),
            'Cliente CRM' => $usuarioModel->obtenerUsuariosPorRolNombre('Cliente CRM'),
            'cliente' => $usuarioModel->obtenerUsuariosPorRolNombre('cliente')
        ];
        
        echo json_encode([
            'roles_disponibles' => $rolesDisponibles,
            'intentos' => array_map(fn($arr) => count($arr), $intentos),
            'search' => $search
        ]);
        exit;
    }
    
    $clientes = $usuarioModel->obtenerUsuariosPorRolNombre('Cliente CRM');

    
    // Filtrar por búsqueda si existe
    if (!empty($search)) {
        $search = strtolower($search);
        $clientes = array_filter($clientes, function($cliente) use ($search) {
            $nombre = strtolower($cliente['nombre'] ?? '');
            $email = strtolower($cliente['email'] ?? '');
            return strpos($nombre, $search) !== false || strpos($email, $search) !== false;
        });
    }
    
    // Formatear para Select2
    $resultado = array_map(function($cliente) {
        return [
            'id' => $cliente['id'],
            'text' => $cliente['nombre'] . ' (' . $cliente['email'] . ')'
        ];
    }, array_values($clientes));
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    error_log("Error listando clientes: " . $e->getMessage());
    if ($debug) {
        echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    } else {
        echo json_encode([]);
    }
}
