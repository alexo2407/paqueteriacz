<?php
/**
 * API Endpoint para listar clientes (para Select2)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modelo/usuario.php';
require_once __DIR__ . '/../../utils/crm_roles.php';
require_once __DIR__ . '/../../utils/session.php';

// Usar sesión segura
start_secure_session();

// Fallback para diferentes claves de sesión
$userId = $_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? $_SESSION['ID_Usuario'] ?? 0;

// Modo debug
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

if ($userId <= 0) {
    if ($debug) {
        echo json_encode(['error' => 'No authenticated', 'session' => array_keys($_SESSION)]);
    } else {
        echo json_encode([]);
    }
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
