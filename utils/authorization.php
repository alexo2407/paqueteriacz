<?php
/**
 * Authorization Helper
 * 
 * Provides centralized functions to check user permissions and roles.
 * Depends on `utils/session.php` being active and `crm_roles.php`/`session` data.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/crm_roles.php';

// Role-based access control uses the names defined in config/config.php:
// ROL_NOMBRE_ADMIN, ROL_NOMBRE_PROVEEDOR, ROL_NOMBRE_CLIENTE, ROL_NOMBRE_REPARTIDOR

/**
 * Verifica si el usuario está logueado. Si no, redirige al login.
 */
function require_login() {
    start_secure_session();
    if (empty($_SESSION['user_id']) && empty($_SESSION['idUsuario']) && empty($_SESSION['registrado'])) {
        header('Location: ' . (defined('RUTA_URL') ? RUTA_URL : '/paqueteriacz/') . 'login');
        exit;
    }
}

/**
 * Verifica si el usuario tiene uno de los roles permitidos.
 * Si no, redirige al dashboard o página de error con mensaje flash.
 * 
 * @param string|array $modulosPermitidos Strings de roles permitidos, ej: ['Administrador', 'Proveedor']
 */
function require_role($rolesPermitidos) {
    require_login(); // Ensure logged in first

    if (is_string($rolesPermitidos)) {
        $rolesPermitidos = [$rolesPermitidos];
    }

    $userRoles = $_SESSION['roles_nombres'] ?? [];
    
    // Fail-safe: si no hay roles en sesión, intentar recargar o asumir vacío
    if (empty($userRoles) && !empty($_SESSION['rol_nombre'])) {
        $userRoles[] = $_SESSION['rol_nombre'];
    }

    // Check intersection
    $hasRole = false;
    foreach ($rolesPermitidos as $role) {
        if (in_array($role, $userRoles)) {
            $hasRole = true;
            break;
        }
    }

    if (!$hasRole) {
        // Access Denied
        set_flash('error', 'No tienes permisos para acceder a esta sección.');
        
        // Redirect logic based on what they ARE allowed to see
        if (in_array(ROL_NOMBRE_REPARTIDOR, $userRoles)) {
            header('Location: ' . (defined('RUTA_URL') ? RUTA_URL : '/paqueteriacz/') . 'seguimiento/listar');
        } elseif (in_array(ROL_NOMBRE_CLIENTE, $userRoles)) {
            header('Location: ' . (defined('RUTA_URL') ? RUTA_URL : '/paqueteriacz/') . 'logistica/dashboard');
        } else {
            header('Location: ' . (defined('RUTA_URL') ? RUTA_URL : '/paqueteriacz/') . 'dashboard');
        }
        exit;
    }
}

/**
 * Helper rápido para solo Admin
 */
function require_admin() {
    require_role(ROL_NOMBRE_ADMIN);
}
