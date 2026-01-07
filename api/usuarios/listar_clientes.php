<?php
/**
 * API Endpoint para listar clientes (para Select2)
 */

header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../modelo/usuario.php';
require_once __DIR__ . '/../../utils/crm_roles.php';

$userId = $_SESSION['idUsuario'] ?? 0;

if ($userId <= 0) {
    echo json_encode([]);
    exit;
}

// Obtener término de búsqueda
$search = $_GET['q'] ?? '';

try {
    $usuarioModel = new UsuarioModel();
    $clientes = $usuarioModel->obtenerUsuariosPorRolNombre('Cliente');
    
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
    echo json_encode([]);
}
